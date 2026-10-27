<?php

namespace Modules\Telegram\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Modules\Telegram\Services\TelegramGateway;
use RuntimeException;
use Throwable;

/**
 * Delivers one approval alert through the Bot API. Safety mirrors
 * SendTelegramMessage: a rate-limiter bucket per target chat (30/min by
 * default), bounded tries with backoff, and the gateway's row-status guard
 * so an at-least-once retry never sends the same alert twice. Telegram's
 * flood-wait (429) releases the job for exactly the requested duration.
 */
class SendTelegramAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [5, 15, 30];

    public function __construct(
        public readonly string $messageId,
        public readonly int $chatId,
    ) {
        $this->onQueue('telegram');
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
        $outcome = $gateway->deliverBotAlert($this->messageId);

        if ($outcome->status === 'throttled') {
            $this->release($outcome->retryAfter ?? 60);

            return;
        }

        if ($outcome->status === 'failed') {
            $this->fail(new RuntimeException($outcome->error ?? 'send_failed'));
        }
    }

    public function failed(?Throwable $exception): void
    {
        $error = $exception?->getMessage() ?? 'send_failed';

        // Never persist the bot token (it rides in transport exception URLs).
        $token = (string) config('services.telegram.bot_token');

        if ($token !== '') {
            $error = str_replace($token, '[redacted]', $error);
        }

        app(TelegramGateway::class)->markFailed($this->messageId, $error);
    }
}
