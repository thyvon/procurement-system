<?php

namespace Modules\Telegram\Channels;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Modules\Telegram\Models\TelegramMessage;
use Modules\Telegram\Services\TelegramGateway;
use Throwable;

/**
 * Bot API transport for approval alerts. Runs synchronously inside the
 * notify() call — and therefore inside the approval transaction — so it may
 * only insert the audit row and dispatch the queued delivery job. Any
 * failure is swallowed and logged: an alert problem must never roll back
 * an approval decision.
 */
class TelegramAlertChannel
{
    public function send(User $notifiable, Notification $notification): ?TelegramMessage
    {
        try {
            if (! (bool) config('services.telegram.alerts_enabled', true)) {
                return null;
            }

            if (blank(config('services.telegram.bot_token'))) {
                return null;
            }

            $chatId = $notifiable->telegram_chat_id;

            if ($chatId === null) {
                Log::info('telegram.alert.skipped', [
                    'user_id' => $notifiable->getKey(),
                    'reason' => 'user_not_linked',
                ]);

                return null;
            }

            $payload = $notification->toArray($notifiable);
            $entityId = $payload['entityId'] ?? null;

            if (blank($entityId)) {
                Log::warning('telegram.alert.queue_failed', [
                    'user_id' => $notifiable->getKey(),
                    'reason' => 'notification_missing_entity',
                ]);

                return null;
            }

            return app(TelegramGateway::class)->sendBotAlert(
                chatId: (int) $chatId,
                body: $notification->toTelegram($notifiable),
                idempotencyKey: 'approval-alert:'.$notification->id,
                entityId: (string) $entityId,
                reference: [
                    'reference_type' => 'approval_request',
                    'reference_id' => $payload['requestId'] ?? null,
                ],
            );
        } catch (Throwable $exception) {
            Log::warning('telegram.alert.queue_failed', [
                'user_id' => $notifiable->getKey(),
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
