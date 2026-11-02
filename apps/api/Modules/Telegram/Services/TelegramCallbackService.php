<?php

namespace Modules\Telegram\Services;

use App\Models\User;
use App\Support\Context\EntityContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\Approvals\Models\ApprovalRequest;
use Modules\Approvals\Services\ApprovalService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Inline-button taps on approval alerts (callback_query updates) and the
 * reason replies to reject/return prompts.
 *
 * Approve runs the same ApprovalService::act pipeline as the HTTP endpoint
 * — pending/assignee/authority-band guards included — after binding the
 * tap's entity context (the worker has none). Reject and return park a
 * chat-keyed pending action in cache and force-reply for the comment the
 * API requires, clearing it on reply, Cancel or TTL. Everything runs in
 * the queue behind an already-answered 200: no path rethrows, every tap
 * gets its callback answered (no stuck spinner), and the bot token never
 * enters a log or a message.
 */
class TelegramCallbackService
{
    /**
     * How long a tapped reject/return waits for its reason reply. Keyed per
     * chat, so a second tap simply replaces the first.
     */
    private const STATE_TTL_MINUTES = 10;

    private const PATTERN_ACTION = '/^approval:([0-9A-HJKMNP-TV-Z]{26}):(approve|reject|return)$/Di';

    private const PATTERN_DISCARD = '/^approval:discard$/D';

    public function __construct(
        private readonly ApprovalService $approvals,
    ) {}

    /**
     * One button tap: approve acts immediately; reject/return park the
     * action and prompt for the reason; discard clears a pending prompt.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleCallback(array $payload): void
    {
        $callback = $payload['callback_query'] ?? null;

        if (! is_array($callback)) {
            return;
        }

        $queryId = (string) ($callback['id'] ?? '');
        $chatId = (int) ($callback['message']['chat']['id'] ?? $callback['from']['id'] ?? 0);
        $data = (string) ($callback['data'] ?? '');
        $messageId = (int) ($callback['message']['message_id'] ?? 0);
        $updateId = (string) ($payload['update_id'] ?? uniqid('tg', false));

        if ($queryId === '' || $chatId === 0) {
            Log::info('telegram.callback.ignored', ['reason' => 'incomplete_payload']);

            return;
        }

        $gateway = app(TelegramGateway::class);

        try {
            if (preg_match(self::PATTERN_DISCARD, $data) === 1) {
                $had = $this->recall($chatId) !== null;
                $this->forget($chatId);

                if ($messageId > 0) {
                    $gateway->editMessageReplyMarkup($chatId, $messageId);
                }

                $gateway->answerCallbackQuery($queryId, $had ? 'Cancelled.' : 'Nothing to cancel.');

                return;
            }

            if (preg_match(self::PATTERN_ACTION, $data, $matches) !== 1) {
                $gateway->answerCallbackQuery($queryId, 'This button is no longer available.');

                return;
            }

            [$requestId, $action] = [$matches[1], $matches[2]];

            $user = $this->userFor($chatId);

            if ($user === null) {
                $gateway->answerCallbackQuery($queryId, 'This chat is not linked to an account yet. Send /start in the bot, then tap again.', true);

                return;
            }

            $approval = ApprovalRequest::withoutGlobalScope('entity')->find($requestId);

            if ($approval === null) {
                $gateway->answerCallbackQuery($queryId, 'This approval request no longer exists.');

                return;
            }

            app()->instance(EntityContext::class, new EntityContext($approval->entity_id));

            if ($action === 'approve') {
                $error = $this->attemptAct($approval, 'approve', null, $user);

                if ($error !== null) {
                    $gateway->answerCallbackQuery($queryId, $error, true);

                    return;
                }

                $this->forget($chatId);
                $gateway->answerCallbackQuery($queryId, 'Approved.');

                if ($messageId > 0) {
                    $gateway->editMessageReplyMarkup($chatId, $messageId);
                }

                return;
            }

            // reject / return — park the action and force a reason reply.
            $this->remember($chatId, [
                'request_id' => (string) $approval->getKey(),
                'action' => $action,
                'user_id' => (int) $user->getKey(),
                'alert_message_id' => $messageId,
            ]);

            if ($this->sendPrompt($approval, $chatId, $action, $updateId)) {
                $gateway->answerCallbackQuery($queryId, 'Send the reason in your reply.');

                return;
            }

            $this->forget($chatId);
            $gateway->answerCallbackQuery($queryId, 'Could not send a prompt here. Try again in the app.', true);
        } catch (Throwable $exception) {
            Log::warning('telegram.callback.failed', ['error' => $exception->getMessage()]);
            $gateway->answerCallbackQuery($queryId, 'Something went wrong. Try again in the app.', true);
        }
    }

    /**
     * The reason reply to a reject/return prompt. A /start code keeps
     * working even while a prompt is pending; anything else consumes the
     * parked action (chat-keyed) and runs the same act pipeline.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleActionReply(array $payload): void
    {
        $message = $payload['message'] ?? null;

        if (! is_array($message)) {
            return;
        }

        $chatId = (int) ($message['chat']['id'] ?? 0);
        $text = trim((string) ($message['text'] ?? ''));
        $updateId = (string) ($payload['update_id'] ?? uniqid('tg', false));

        if ($chatId === 0 || $text === '') {
            return;
        }

        if (preg_match('/^\/start(?:@\w+)?(?:\s|$)/', $text) === 1) {
            app(TelegramLinkService::class)->handleStart($payload);

            return;
        }

        $state = $this->recall($chatId);

        if ($state === null) {
            return;
        }

        $user = $this->userFor($chatId);

        if ($user === null || (int) $user->getKey() !== (int) ($state['user_id'] ?? 0)) {
            $this->forget($chatId);
            Log::info('telegram.action.reply_mismatch', ['chat_id' => $chatId]);

            return;
        }

        $approval = ApprovalRequest::withoutGlobalScope('entity')->find((string) ($state['request_id'] ?? ''));

        if ($approval === null) {
            $this->forget($chatId);
            $this->tell($chatId, $payload, e('This approval request no longer exists.'), (string) $user->entity_id, null);

            return;
        }

        app()->instance(EntityContext::class, new EntityContext($approval->entity_id));

        if (mb_strlen($text) > 2000) {
            $this->tell($chatId, $payload, e('A reason may be at most 2000 characters. Please send a shorter reply.'), (string) $approval->entity_id, (string) $approval->getKey());

            return;
        }

        $error = $this->attemptAct($approval, (string) $state['action'], $text, $user);

        $this->forget($chatId);

        if ($error !== null) {
            $this->tell($chatId, $payload, e($error), (string) $approval->entity_id, (string) $approval->getKey());

            return;
        }

        $gateway = app(TelegramGateway::class);
        $alertMessageId = (int) ($state['alert_message_id'] ?? 0);

        if ($alertMessageId > 0) {
            $gateway->editMessageReplyMarkup($chatId, $alertMessageId);
        }

        $promptMessageId = (int) ($message['reply_to_message']['message_id'] ?? 0);

        if ($promptMessageId > 0) {
            $gateway->editMessageReplyMarkup($chatId, $promptMessageId);
        }

        $this->tell($chatId, $payload, $this->confirmation((string) $state['action'], $approval), (string) $approval->entity_id, (string) $approval->getKey());
    }

    /**
     * The HTTP endpoint's act guard + service call, flattened to a plain
     * user-facing error string (null = success). Catches everything so an
     * at-least-once job can never crash on a business rejection.
     */
    private function attemptAct(ApprovalRequest $approval, string $action, ?string $comment, User $user): ?string
    {
        if (! Gate::forUser($user)->allows('act', $approval)) {
            return 'Your account cannot act on approvals.';
        }

        try {
            $this->approvals->act($approval, $action, $comment, $user);
        } catch (ValidationException $exception) {
            $first = collect($exception->errors())->flatten()->first();

            return is_string($first) && $first !== '' ? $first : 'This action is no longer allowed.';
        } catch (HttpException $exception) {
            return $exception->getMessage() !== '' ? $exception->getMessage() : 'You cannot perform this action.';
        } catch (Throwable $exception) {
            Log::warning('telegram.callback.act_failed', [
                'approval_request_id' => $approval->getKey(),
                'action' => $action,
                'error' => $exception->getMessage(),
            ]);

            return 'Something went wrong. Try again in the app.';
        }

        return null;
    }

    /**
     * Force-reply prompt carrying the reason requirement, with a Cancel tap
     * that clears the parked action. Rides sendBotAlert (audit row + queued
     * delivery), keyed on the tap's update so an at-least-once retry can
     * never prompt twice for one tap.
     */
    private function sendPrompt(ApprovalRequest $approval, int $chatId, string $action, string $updateId): bool
    {
        if (blank(config('services.telegram.bot_token')) || blank($approval->entity_id)) {
            Log::warning('telegram.action.prompt_skipped', [
                'approval_request_id' => $approval->getKey(),
                'chat_id' => $chatId,
                'reason' => blank($approval->entity_id) ? 'missing_entity' : 'bot_token_missing',
            ]);

            return false;
        }

        $verb = $action === 'reject' ? 'Reject' : 'Return';
        $icon = $action === 'reject' ? '❌' : '↩️';
        $code = $approval->snapshot['documentCode'] ?? null;

        $body = (is_string($code) && $code !== '')
            ? "{$icon} {$verb} <code>".e($code)."</code>\nSend your reason as a reply to this message."
            : "{$icon} {$verb} this request?\nSend your reason as a reply to this message.";

        app(TelegramGateway::class)->sendBotAlert(
            chatId: $chatId,
            body: $body,
            idempotencyKey: "telegram-action-prompt:{$updateId}",
            entityId: (string) $approval->entity_id,
            reference: [
                'reference_type' => 'telegram_action_prompt',
                'reference_id' => $approval->getKey(),
            ],
        );

        return true;
    }

    /**
     * Confirmation or error as an audited chat message (queued delivery,
     * reference type keeps buttons/PDF off it). $body must already be
     * Telegram-HTML safe — the channel sends with parse_mode=HTML.
     *
     * @param  array<string, mixed>  $payload
     */
    private function tell(int $chatId, array $payload, string $body, string $entityId, ?string $referenceId): void
    {
        if (blank(config('services.telegram.bot_token')) || $entityId === '') {
            Log::warning('telegram.action.reply_skipped', [
                'chat_id' => $chatId,
                'reason' => $entityId === '' ? 'missing_entity' : 'bot_token_missing',
            ]);

            return;
        }

        app(TelegramGateway::class)->sendBotAlert(
            chatId: $chatId,
            body: $body,
            idempotencyKey: 'telegram-action-result:'.((string) ($payload['update_id'] ?? uniqid('tg', false))),
            entityId: $entityId,
            reference: [
                'reference_type' => 'telegram_action_result',
                'reference_id' => $referenceId,
            ],
        );
    }

    private function confirmation(string $action, ApprovalRequest $approval): string
    {
        $icon = match ($action) {
            'approve' => '✅',
            'reject' => '❌',
            'return' => '↩️',
            default => '🔄',
        };
        $verb = match ($action) {
            'approve' => 'Approved',
            'reject' => 'Rejected',
            'return' => 'Returned',
            default => 'Updated',
        };
        $code = $approval->snapshot['documentCode'] ?? null;

        return (is_string($code) && $code !== '')
            ? "{$icon} {$verb} <code>".e($code).'</code>.'
            : "{$icon} {$verb} this request.";
    }

    /**
     * @return array<string, mixed>|null
     */
    private function recall(int $chatId): ?array
    {
        $state = Cache::get($this->stateKey($chatId));

        return is_array($state) ? $state : null;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function remember(int $chatId, array $state): void
    {
        Cache::put($this->stateKey($chatId), $state, now()->addMinutes(self::STATE_TTL_MINUTES));
    }

    private function forget(int $chatId): void
    {
        Cache::forget($this->stateKey($chatId));
    }

    private function stateKey(int $chatId): string
    {
        return "telegram:approval-action:{$chatId}";
    }

    private function userFor(int $chatId): ?User
    {
        return User::withoutGlobalScope('entity')->where('telegram_chat_id', $chatId)->first();
    }
}
