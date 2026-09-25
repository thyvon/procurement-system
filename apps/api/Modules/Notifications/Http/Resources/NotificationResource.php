<?php

namespace Modules\Notifications\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->data['type'] ?? null,
            'data' => $this->data,
            'readAt' => $this->read_at?->format('Y-m-d H:i'),
            'createdAt' => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}
