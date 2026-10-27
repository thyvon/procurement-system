<?php

namespace Modules\Approvals\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\Approvals\Services\ApprovalAlertMessage;
use Modules\Telegram\Channels\TelegramAlertChannel;

class ActionRequiredNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(private readonly array $context) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', TelegramAlertChannel::class];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'approval.action_required',
            ...$this->context,
        ];
    }

    /**
     * Plain text for the Bot API channel; wording lives in the shared
     * builder so every future transport says the same thing.
     */
    public function toTelegram(object $notifiable): string
    {
        return ApprovalAlertMessage::actionRequired($this->context);
    }
}
