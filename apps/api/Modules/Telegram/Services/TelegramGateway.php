<?php

namespace Modules\Telegram\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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

        SendTelegramMessage::dispatch($message->id, $account->id);

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
