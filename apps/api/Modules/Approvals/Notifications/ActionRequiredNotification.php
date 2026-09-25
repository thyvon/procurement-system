<?php

namespace Modules\Approvals\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

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
        return ['database'];
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
}
