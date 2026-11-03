<?php

namespace Modules\Telegram\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Approvals\Events\ApprovalActed;
use Modules\Telegram\Jobs\EditTelegramAlert;
use Modules\Telegram\Models\TelegramMessage;

/**
 * Approval decisions — from the web app or a Telegram button tap — land in
 * ApprovalService::act()/cancel(), so this handler sees both transports.
 * It queues one in-place edit per already-audited alert for the request;
 * the jobs run afterCommit and re-read the committed decision (final
 * result vs. round-continues) before touching Telegram.
 */
class UpdateTelegramAlertsOnDecision
{
    public function handle(ApprovalActed $event): void
    {
        if (! (bool) config('services.telegram.alert_edits_enabled', true)) {
            return;
        }

        if (blank(config('services.telegram.bot_token'))) {
            return;
        }

        $messages = TelegramMessage::withoutGlobalScope('entity')
            ->where('direction', TelegramMessage::DIRECTION_OUT)
            ->where('reference_type', 'approval_request')
            ->where('reference_id', $event->requestId)
            ->whereIn('status', [TelegramMessage::STATUS_QUEUED, TelegramMessage::STATUS_SENT])
            ->whereNotNull('telegram_chat_id')
            ->get(['id', 'telegram_chat_id']);

        foreach ($messages as $message) {
            EditTelegramAlert::dispatch(
                (string) $message->getKey(),
                (int) $message->telegram_chat_id,
                [
                    'requestId' => $event->requestId,
                    'action' => $event->action,
                    'actorId' => $event->actorId,
                    'comment' => $event->comment,
                    'stepLabel' => $event->stepLabel,
                ],
            );
        }

        if ($messages->isNotEmpty()) {
            Log::info('telegram.alert.edit_queued', [
                'request_id' => $event->requestId,
                'action' => $event->action,
                'count' => $messages->count(),
            ]);
        }
    }
}
