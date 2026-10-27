<?php

namespace Modules\Telegram\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Telegram\Services\TelegramLinkService;

/**
 * Processes one verified Bot API webhook payload: extract the /start code,
 * resolve it against telegram_link_codes, bind the chat to the user and
 * reply in the issuing chat.
 */
class ProcessTelegramLinkStart implements ShouldQueue
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

    public function handle(TelegramLinkService $service): void
    {
        $service->handleStart($this->payload);
    }
}
