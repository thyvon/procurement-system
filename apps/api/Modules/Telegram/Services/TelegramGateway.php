<?php

namespace Modules\Telegram\Services;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Approvals\Models\ApprovalRequest;
use Modules\Approvals\Services\ApprovalAlertMessage;
use Modules\Approvals\Services\ApprovalDocumentPrinter;
use Modules\Telegram\Jobs\SendTelegramAlert;
use Modules\Telegram\Jobs\SendTelegramMessage;
use Modules\Telegram\Models\TelegramAccount;
use Modules\Telegram\Models\TelegramContact;
use Modules\Telegram\Models\TelegramMessage;
use RuntimeException;

/**
 * Single entry point for everything that talks to the TDLib service:
 * validation, account routing, idempotency, delivery and status transitions.
 * Queue-side lookups are intentionally unscoped by entity — the worker has no
 * request context, and every lookup goes through unguessable ULIDs or the
 * globally-unique Telegram ids.
 */
class TelegramGateway
{
    /**
     * Create an outbound message and queue it for the contact's fixed account.
     * An already-known idempotency key returns the existing message untouched
     * (replay-safe), which the controller reports as 200 instead of 201.
     *
     * @param  array<string, mixed>  $reference  reference_type/reference_id for future RFQ/PO linking
     */
    public function sendToContact(
        TelegramContact $contact,
        string $body,
        ?string $idempotencyKey = null,
        ?User $user = null,
        array $reference = [],
    ): TelegramMessage {
        $key = $idempotencyKey ?: (string) Str::uuid();

        $existing = TelegramMessage::query()->where('idempotency_key', $key)->first();

        if ($existing !== null) {
            return $existing;
        }

        $account = $contact->telegramAccount;

        if ($account === null) {
            abort(422, 'Contact has no Telegram account assigned.');
        }

        if ($account->state !== TelegramAccount::STATE_READY) {
            abort(422, "Telegram account is not ready (state: {$account->state}).");
        }

        try {
            $message = TelegramMessage::create([
                'entity_id' => $contact->entity_id,
                'direction' => TelegramMessage::DIRECTION_OUT,
                'telegram_account_id' => $account->id,
                'telegram_contact_id' => $contact->id,
                'telegram_chat_id' => $contact->telegram_chat_id,
                'reference_type' => $reference['reference_type'] ?? null,
                'reference_id' => $reference['reference_id'] ?? null,
                'body' => $body,
                'status' => TelegramMessage::STATUS_QUEUED,
                'idempotency_key' => $key,
                'created_by' => $user?->getKey(),
                'updated_by' => $user?->getKey(),
            ]);
        } catch (QueryException $exception) {
            // A concurrent writer won the insert between our SELECT and
            // INSERT — replay-safe: hand back their row instead of failing.
            if ($this->isUniqueViolation($exception)) {
                $existing = TelegramMessage::query()->where('idempotency_key', $key)->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            throw $exception;
        }

        SendTelegramMessage::dispatch($message->id, $account->id);

        return $message;
    }

    /**
     * Queue one Bot API alert (approval notifications): no TDLib account
     * and no contact — only a user's direct chat, so telegram_account_id
     * and telegram_contact_id stay null and the transport is Bot API.
     * Replay-safe like sendToContact(): a known idempotency key returns the
     * existing row untouched instead of queueing a second delivery.
     *
     * @param  array<string, mixed>  $reference  reference_type/reference_id (approval_request link)
     */
    public function sendBotAlert(
        int $chatId,
        string $body,
        string $idempotencyKey,
        string $entityId,
        array $reference = [],
    ): TelegramMessage {
        $existing = TelegramMessage::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            $message = TelegramMessage::create([
                'entity_id' => $entityId,
                'direction' => TelegramMessage::DIRECTION_OUT,
                'telegram_account_id' => null,
                'telegram_contact_id' => null,
                'telegram_chat_id' => $chatId,
                'reference_type' => $reference['reference_type'] ?? null,
                'reference_id' => $reference['reference_id'] ?? null,
                'body' => $body,
                'status' => TelegramMessage::STATUS_QUEUED,
                'idempotency_key' => $idempotencyKey,
            ]);
        } catch (QueryException $exception) {
            // A concurrent writer won the insert between our SELECT and
            // INSERT — replay-safe: hand back their row instead of failing.
            if ($this->isUniqueViolation($exception)) {
                $existing = TelegramMessage::query()->where('idempotency_key', $idempotencyKey)->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            throw $exception;
        }

        SendTelegramAlert::dispatch($message->id, $chatId);

        return $message;
    }

    /**
     * One delivery attempt. Called by SendTelegramMessage inside the queue
     * worker (no entity context — all lookups are unscoped by ULID).
     */
    public function deliver(string $messageId): SendOutcome
    {
        $message = TelegramMessage::withoutGlobalScope('entity')->find($messageId);

        if ($message === null) {
            return SendOutcome::failed('message_not_found');
        }

        $account = TelegramAccount::withoutGlobalScope('entity')->find($message->telegram_account_id);
        $contact = TelegramContact::withoutGlobalScope('entity')->find($message->telegram_contact_id);

        if ($account === null || $contact === null) {
            $this->markFailed($messageId, 'account_or_contact_missing');

            return SendOutcome::failed('account_or_contact_missing');
        }

        if ($account->state === TelegramAccount::STATE_THROTTLED && $account->flood_wait_until !== null) {
            $remaining = (int) now()->diffInSeconds($account->flood_wait_until, false);

            if ($remaining > 0) {
                return SendOutcome::throttled($remaining);
            }

            $account->update(['state' => TelegramAccount::STATE_READY, 'flood_wait_until' => null]);
        }

        if ($account->state !== TelegramAccount::STATE_READY) {
            $this->markFailed($messageId, "account_not_ready:{$account->state}");

            return SendOutcome::failed("account_not_ready:{$account->state}");
        }

        $this->jitter();

        $baseUrl = $account->tdlib_base_url ?? config('services.telegram.base_url');

        if (blank($baseUrl)) {
            $this->markFailed($messageId, 'tdlib_base_url_not_configured');

            return SendOutcome::failed('tdlib_base_url_not_configured');
        }

        $response = Http::withToken((string) config('services.telegram.service_token'))
            ->timeout(20)
            ->post(rtrim($baseUrl, '/').'/send', [
                'chat_id' => $contact->telegram_chat_id,
                'phone' => $contact->phone,
                'username' => $contact->username,
                'text' => $message->body,
            ]);

        $status = $response->status();

        if ($status === 200) {
            $sentChatId = (int) ($response->json('chat_id') ?? $contact->telegram_chat_id ?? 0);

            $message->update([
                'status' => TelegramMessage::STATUS_SENT,
                'telegram_chat_id' => $sentChatId,
                'telegram_message_id' => (int) $response->json('message_id') ?: null,
                'error' => null,
            ]);

            // Persist the pod-resolved chat onto the contact so inbound
            // replies can be matched (InboundTelegramService keys on it).
            if ($sentChatId && $contact->telegram_chat_id !== $sentChatId) {
                $contact->update(['telegram_chat_id' => $sentChatId]);
            }

            $account->update(['last_seen_at' => now()]);

            return SendOutcome::sent();
        }

        if ($status === 429) {
            $retryAfter = (int) $response->json('retryAfter')
                ?: (int) $response->header('Retry-After')
                ?: 60;

            $account->update([
                'state' => TelegramAccount::STATE_THROTTLED,
                'flood_wait_until' => now()->addSeconds($retryAfter),
            ]);

            return SendOutcome::throttled($retryAfter);
        }

        if ($status >= 500) {
            throw new RuntimeException("tdlib_unavailable:{$status}");
        }

        $error = $response->json('error', "send_failed:{$status}");
        $this->markFailed($messageId, (string) $error);

        return SendOutcome::failed((string) $error);
    }

    /**
     * One Bot API delivery attempt for a queued alert. Called by
     * SendTelegramAlert inside the queue worker (no entity context — the
     * lookup is unscoped by ULID). A row already marked sent short-circuits
     * so an at-least-once queue retry can never send the alert twice.
     * Approval alerts go as sendDocument with the alert text as caption and
     * the subject's A4 print sheet attached; while the request is pending
     * they also carry the inline approve/reject/return buttons, and
     * reject/return prompts go out as force-reply messages with a Cancel
     * button. Everything else is plain sendMessage text.
     *
     * The bot token rides in the request URL, so raw transport exceptions
     * are re-thrown with static messages only — no path can persist it.
     */
    public function deliverBotAlert(string $messageId): SendOutcome
    {
        $message = TelegramMessage::withoutGlobalScope('entity')->find($messageId);

        if ($message === null) {
            return SendOutcome::failed('message_not_found');
        }

        if ($message->status === TelegramMessage::STATUS_SENT) {
            return SendOutcome::sent();
        }

        $token = (string) config('services.telegram.bot_token');

        if ($token === '') {
            $this->markFailed($messageId, 'bot_token_not_configured');

            return SendOutcome::failed('bot_token_not_configured');
        }

        $approval = $this->alertApproval($message);
        $document = $this->alertDocument($approval);
        $markup = $this->alertMarkup($message, $approval);

        $url = 'https://api.telegram.org/bot'.$token.($document === null ? '/sendMessage' : '/sendDocument');
        $payload = [
            'chat_id' => $message->telegram_chat_id,
            'parse_mode' => 'HTML',
        ];
        $payload[$document === null ? 'text' : 'caption'] = $message->body;

        if ($markup !== null) {
            $payload['reply_markup'] = $markup;
        }

        try {
            $http = Http::withToken($token)
                ->timeout(15)
                ->connectTimeout(5);

            $response = $document === null
                ? $http->post($url, $payload)
                : $http->attach('document', $document['binary'], $document['filename'])
                    ->post($url, $payload);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('telegram_api_unreachable:'.$exception->getCode());
        }

        $status = $response->status();

        if ($status === 200 && $response->json('ok') === true) {
            $message->update([
                'status' => TelegramMessage::STATUS_SENT,
                'telegram_message_id' => (int) $response->json('result.message_id') ?: null,
                'error' => null,
                // Which edit endpoint this row answers to later: alerts sent
                // as sendDocument carry a caption, plain alerts a text body.
                'parsed_json' => array_merge($message->parsed_json ?? [], [
                    'delivery' => $document === null ? 'text' : 'document',
                ]),
            ]);

            Log::info('telegram.alert.sent', [
                'message_id' => $message->getKey(),
                'chat_id' => $message->telegram_chat_id,
                'reference_type' => $message->reference_type,
                'reference_id' => $message->reference_id,
                'attached' => $document !== null,
                'buttons' => $markup !== null,
            ]);

            return SendOutcome::sent();
        }

        if ($status === 429) {
            $retryAfter = (int) $response->json('parameters.retry_after')
                ?: (int) $response->header('Retry-After')
                ?: 60;

            return SendOutcome::throttled($retryAfter);
        }

        if ($status >= 500) {
            throw new RuntimeException("telegram_api_unavailable:{$status}");
        }

        $error = $response->json('description', "send_failed:{$status}");
        $this->markFailed($messageId, (string) $error);

        return SendOutcome::failed((string) $error);
    }

    /**
     * The approval request an alert row refers to, loaded unscoped (the
     * worker has no entity context). Null for every other reference kind.
     */
    private function alertApproval(TelegramMessage $message): ?ApprovalRequest
    {
        if ($message->reference_type !== 'approval_request' || $message->reference_id === null) {
            return null;
        }

        return ApprovalRequest::withoutGlobalScope('entity')->find($message->reference_id);
    }

    /**
     * In-place rewrite of an already-delivered approval alert once its
     * request is decided — the single transport both the Telegram button
     * path and the web app go through, so the chat always reflects the
     * committed outcome. The new body replaces the old one and the inline
     * keyboard is cleared in the same call; the audit row's body is only
     * updated after Telegram confirms (or reports "message is not
     * modified", meaning the chat already shows this exact content).
     *
     * $context carries the decision: requestId/action/actorId/comment/
     * stepLabel (see ApprovalActed). A still-pending request only has the
     * actor's own alert rewritten — every other co-signer's "action
     * required" message must keep its buttons.
     *
     * @param  array<string, mixed>  $context
     */
    public function editAlertMessage(string $messageId, array $context): SendOutcome
    {
        $message = TelegramMessage::withoutGlobalScope('entity')->find($messageId);

        if ($message === null) {
            return SendOutcome::failed('message_not_found');
        }

        if ($message->telegram_message_id === null || $message->telegram_chat_id === null) {
            return SendOutcome::failed('not_delivered_yet');
        }

        $token = (string) config('services.telegram.bot_token');

        if ($token === '') {
            return SendOutcome::failed('bot_token_not_configured');
        }

        $body = $this->editedAlertBody($message, $context);

        if ($body === null) {
            // Another co-signer still owes a decision — their message stays.
            return SendOutcome::sent();
        }

        $asDocument = ($message->parsed_json['delivery'] ?? 'text') === 'document';
        $method = $asDocument ? 'editMessageCaption' : 'editMessageText';
        $payload = [
            'chat_id' => $message->telegram_chat_id,
            'message_id' => $message->telegram_message_id,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => []], JSON_THROW_ON_ERROR),
        ];
        $payload[$asDocument ? 'caption' : 'text'] = $body;

        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->connectTimeout(5)
                ->post('https://api.telegram.org/bot'.$token.'/'.$method, $payload);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('telegram_api_unreachable:'.$exception->getCode());
        }

        $status = $response->status();

        if ($status === 200 && $response->json('ok') === true) {
            $this->syncEditedBody($message, $body);

            Log::info('telegram.alert.edited', [
                'message_id' => $message->getKey(),
                'chat_id' => $message->telegram_chat_id,
                'method' => $method,
                'request_id' => $context['requestId'] ?? null,
                'action' => $context['action'] ?? null,
            ]);

            return SendOutcome::sent();
        }

        if ($status === 429) {
            $retryAfter = (int) $response->json('parameters.retry_after')
                ?: (int) $response->header('Retry-After')
                ?: 60;

            return SendOutcome::throttled($retryAfter);
        }

        if ($status >= 500) {
            throw new RuntimeException("telegram_api_unavailable:{$status}");
        }

        $description = (string) $response->json('description', "edit_failed:{$status}");

        // Idempotent retry: the chat already shows this exact content.
        if (str_contains(strtolower($description), 'message is not modified')) {
            $this->syncEditedBody($message, $body);

            return SendOutcome::sent();
        }

        return SendOutcome::failed($description);
    }

    /**
     * The replacement copy for one alert row: the final result once the
     * request is no longer pending, the "you approved" state while the
     * round continues (actor's row only), or null to leave the row alone.
     *
     * @param  array<string, mixed>  $context
     */
    private function editedAlertBody(TelegramMessage $message, array $context): ?string
    {
        $approval = $this->alertApproval($message);

        if ($approval === null) {
            return null;
        }

        $actor = (int) ($context['actorId'] ?? 0) > 0
            ? User::withoutGlobalScope('entity')->find((int) $context['actorId'])
            : null;

        $fields = [
            'subjectType' => $approval->subject_type,
            'documentCode' => $approval->snapshot['documentCode'] ?? null,
            'amount' => number_format((float) $approval->amount_snapshot, 2, '.', ''),
            'requestId' => (string) $approval->getKey(),
            'submittedBy' => $approval->submittedByLine(),
        ];

        if ($approval->status === ApprovalRequest::STATUS_PENDING) {
            $actorChat = $actor?->telegram_chat_id;

            if ($actorChat === null || (int) $actorChat !== (int) $message->telegram_chat_id) {
                return null;
            }

            return ApprovalAlertMessage::acted($fields + [
                'stepLabel' => $context['stepLabel'] ?? null,
            ]);
        }

        return ApprovalAlertMessage::result($fields + [
            'status' => $approval->status,
            'decidedBy' => $actor?->name,
            'reason' => $context['comment'] ?? null,
        ]);
    }

    /**
     * Keep the audit row truthful after a confirmed edit: the chat now
     * shows $body, and edited_at marks the in-place rewrite.
     */
    private function syncEditedBody(TelegramMessage $message, string $body): void
    {
        $message->update([
            'body' => $body,
            'parsed_json' => array_merge($message->parsed_json ?? [], [
                'edited_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Print-sheet attachment for approval alerts: renders the subject's A4
     * PDF in the worker. Null (plain text alert) when the flag is off, the
     * row references nothing, or rendering fails — the printer already
     * logged why.
     *
     * @return array{binary: string, filename: string}|null
     */
    private function alertDocument(?ApprovalRequest $approval): ?array
    {
        if (! (bool) config('services.telegram.alert_pdf_enabled', true)) {
            return null;
        }

        if ($approval === null) {
            return null;
        }

        return app(ApprovalDocumentPrinter::class)->forRequest($approval);
    }

    /**
     * Inline keyboard for the delivery: action-required alerts of a still-
     * pending request carry approve/reject/return taps (flagged), and the
     * reject/return reason prompts force a reply with a Cancel button.
     * Result alerts and everything else stay markup-free.
     */
    private function alertMarkup(TelegramMessage $message, ?ApprovalRequest $approval): ?string
    {
        if ($message->reference_type === 'telegram_action_prompt') {
            return json_encode([
                'force_reply' => true,
                'input_field_placeholder' => 'Send the reason as a reply…',
                'inline_keyboard' => [
                    [['text' => '✖ Cancel', 'callback_data' => 'approval:discard']],
                ],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        if (! (bool) config('services.telegram.alert_actions_enabled', true)) {
            return null;
        }

        if ($approval === null || ! $approval->isPending()) {
            return null;
        }

        $id = (string) $approval->getKey();

        return json_encode([
            'inline_keyboard' => [
                [['text' => '✅ Approve', 'callback_data' => "approval:{$id}:approve"]],
                [
                    ['text' => '❌ Reject', 'callback_data' => "approval:{$id}:reject"],
                    ['text' => '↩️ Return', 'callback_data' => "approval:{$id}:return"],
                ],
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Acknowledge a button tap so the chat never shows a stuck spinner.
     * Best-effort: a re-answer Telegram rejects is simply logged. Errors
     * carry Telegram's description only — never the token from the URL.
     */
    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false): bool
    {
        $token = (string) config('services.telegram.bot_token');

        if ($token === '') {
            return false;
        }

        $payload = [
            'callback_query_id' => $callbackQueryId,
            'show_alert' => $showAlert,
        ];

        if ($text !== null) {
            $payload['text'] = Str::limit($text, 190);
        }

        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->connectTimeout(5)
                ->post('https://api.telegram.org/bot'.$token.'/answerCallbackQuery', $payload);
        } catch (ConnectionException $exception) {
            Log::warning('telegram.callback.answer_failed', ['error' => 'unreachable:'.$exception->getCode()]);

            return false;
        }

        if ($response->status() !== 200 || $response->json('ok') !== true) {
            Log::warning('telegram.callback.answer_failed', [
                'status' => $response->status(),
                'error' => $response->json('description'),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Strip the inline buttons from an already-delivered message (used once
     * an action lands so the keyboard can never be tapped twice). Best-
     * effort with the same token-safe error handling as above.
     */
    public function editMessageReplyMarkup(int $chatId, int $messageId): bool
    {
        $token = (string) config('services.telegram.bot_token');

        if ($token === '') {
            return false;
        }

        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->connectTimeout(5)
                ->post('https://api.telegram.org/bot'.$token.'/editMessageReplyMarkup', [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'reply_markup' => json_encode(['inline_keyboard' => []], JSON_THROW_ON_ERROR),
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('telegram.callback.markup_edit_failed', ['error' => 'unreachable:'.$exception->getCode()]);

            return false;
        }

        if ($response->status() !== 200 || $response->json('ok') !== true) {
            Log::warning('telegram.callback.markup_edit_failed', [
                'status' => $response->status(),
                'error' => $response->json('description'),
            ]);

            return false;
        }

        return true;
    }

    public function markFailed(string $messageId, string $error): void
    {
        TelegramMessage::withoutGlobalScope('entity')
            ->whereKey($messageId)
            ->where('status', '!=', TelegramMessage::STATUS_SENT)
            ->update([
                'status' => TelegramMessage::STATUS_FAILED,
                'error' => Str::limit($error, 500),
                'updated_at' => now(),
            ]);
    }

    /**
     * SQLSTATE 23xxx integrity-constraint violation — what MySQL and SQLite
     * raise for a duplicate unique key (idempotency_key racing a concurrent
     * insert).
     */
    private function isUniqueViolation(QueryException $exception): bool
    {
        return (string) ($exception->errorInfo[0] ?? $exception->getCode()) === '23000';
    }

    /**
     * Forward the login code to the account's pod. The code itself is never
     * logged or persisted — only the resulting state comes back.
     *
     * @return array<string, mixed>
     */
    public function loginCode(TelegramAccount $account, string $code): array
    {
        return $this->postToPod($account, '/auth/code', ['code' => $code]);
    }

    /**
     * @return array<string, mixed>
     */
    public function loginPassword(TelegramAccount $account, string $password): array
    {
        return $this->postToPod($account, '/auth/password', ['password' => $password]);
    }

    /**
     * Proxy the pod's health and mirror its state back onto the account row.
     *
     * @return array<string, mixed>
     */
    public function health(TelegramAccount $account): array
    {
        $payload = $this->postToPod($account, '/health', [], 'GET');

        $state = $payload['state'] ?? null;

        if (is_string($state) && in_array($state, TelegramAccount::STATES, true)) {
            $account->update([
                'state' => $state,
                'last_seen_at' => now(),
                'flood_wait_until' => null,
            ]);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function postToPod(TelegramAccount $account, string $path, array $body, string $method = 'POST'): array
    {
        $baseUrl = $account->tdlib_base_url ?? config('services.telegram.base_url');

        if (blank($baseUrl)) {
            abort(422, 'TDLib service URL is not configured.');
        }

        $pending = Http::withToken((string) config('services.telegram.service_token'))->timeout(15);

        $response = match ($method) {
            'GET' => $pending->get(rtrim($baseUrl, '/').$path),
            default => $pending->post(rtrim($baseUrl, '/').$path, $body),
        };

        if ($response->failed()) {
            abort($response->status() >= 500 ? 502 : 422, $response->json('error', 'TDLib service request failed.'));
        }

        return $response->json() ?? [];
    }

    /**
     * Random send delay (TELEGRAM_JITTER_MAX_MS, 0 in tests) so bursts never
     * look like scripted traffic to Telegram.
     */
    private function jitter(): void
    {
        $maxMs = (int) config('services.telegram.jitter_max_ms', 0);

        if ($maxMs > 0) {
            usleep(random_int(200_000, $maxMs * 1000));
        }
    }
}
