<?php

namespace Modules\Telegram\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Telegram\Services\TelegramGateway;
use RuntimeException;
use Throwable;

/**
 * Rewrites one already-delivered approval alert in place after its request
 * is decided — the edit path shared by Telegram button taps and web-app
 * decisions. Safety mirrors SendTelegramAlert: dispatched afterCommit (the
 * decision transaction must be visible first), same per-chat rate-limiter
 * bucket, bounded tries with backoff, and 429 flood-waits release for the
 * requested duration. A row that has not been delivered yet is released
 * briefly so the edit can never outrun the original send.
 */
class EditTelegramAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [5, 15, 30];

    /**
     * @param  array<string, mixed>  $context  ApprovalActed decision: requestId/action/actorId/comment/stepLabel
     */
    public function __construct(
        public readonly string $messageId,
        public readonly int $chatId,
        public readonly array $context,
    ) {
        $this->onQueue('telegram');
        $this->afterCommit();
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            new RateLimited('telegram-alert'),
        ];
    }

    public function handle(TelegramGateway $gateway): void
    {
        $outcome = $gateway->editAlertMessage($this->messageId, $this->context);

        if ($outcome->status === 'throttled') {
            $this->release($outcome->retryAfter ?? 60);

            return;
        }

        if ($outcome->status === 'failed' && $outcome->error === 'not_delivered_yet') {
            // The original send is still in flight — retry shortly, bounded
            // by $tries, so a fast decision can never outrun delivery.
            $this->release(3);

            return;
        }

        if ($outcome->status === 'failed') {
            $this->fail(new RuntimeException($outcome->error ?? 'edit_failed'));
        }
    }

    public function failed(?Throwable $exception): void
    {
        $error = $exception?->getMessage() ?? 'edit_failed';

        // Never persist the bot token (it rides in transport exception URLs).
        $token = (string) config('services.telegram.bot_token');

        if ($token !== '') {
            $error = str_replace($token, '[redacted]', $error);
        }

        // The alert itself stays delivered — only the rewrite failed.
        Log::warning('telegram.alert.edit_failed', [
            'message_id' => $this->messageId,
            'chat_id' => $this->chatId,
            'error' => $error,
        ]);
    }
}
