<?php

namespace Modules\Telegram\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Telegram\Services\TelegramCallbackService;

/**
 * Processes one chat message that answers a reject/return prompt: the
 * pending action parked by the button tap is still cached for this chat,
 * so the reason text becomes the comment on ApprovalService::act. Routed
 * by BotWebhookController while that marker exists.
 */
class ProcessTelegramActionReply implements ShouldQueue
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
        $service->handleActionReply($this->payload);
    }
}
