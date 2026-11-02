<?php

namespace Modules\Telegram\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Telegram\Services\TelegramCallbackService;

/**
 * Processes one verified Bot API callback_query: an inline-button tap on
 * an approval alert (approve acts; reject/return prompt for the reason;
 * discard clears a pending prompt). The webhook already answered 200 —
 * every path answers the callback inside the job.
 */
class ProcessTelegramCallback implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [5, 15];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly array $payload,
    ) {
        $this->onQueue('telegram');
    }

    public function handle(TelegramCallbackService $service): void
    {
        $service->handleCallback($this->payload);
    }
}
