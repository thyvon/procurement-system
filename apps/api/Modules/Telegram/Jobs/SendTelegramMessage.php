<?php

namespace Modules\Telegram\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Modules\Telegram\Services\TelegramGateway;
use RuntimeException;
use Throwable;

/**
 * Delivers one outbound message through the account's TDLib pod.
 *
 * Per-account safety lives in the middleware: a shared rate limiter bucket
 * keyed by accountId (5/min by default) and an overlap lock per account, so
 * one account's traffic can never interleave with itself. Flood-wait from
 * Telegram releases the job for exactly the requested duration while the
 * gateway marks the account throttled.
 */
class SendTelegramMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /**
     * @var array<int, int>
     */
    public array $backoff = [5, 15, 30, 60];

    public function __construct(
        public readonly string $messageId,
        public readonly string $accountId,
    ) {
        $this->onQueue('telegram');
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            new RateLimited('telegram-account'),
            new WithoutOverlapping("telegram:{$this->accountId}")->releaseAfter(10),
        ];
    }

    public function handle(TelegramGateway $gateway): void
    {
        $outcome = $gateway->deliver($this->messageId);

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
        app(TelegramGateway::class)->markFailed(
            $this->messageId,
            $exception?->getMessage() ?? 'send_failed',
        );
    }
}
