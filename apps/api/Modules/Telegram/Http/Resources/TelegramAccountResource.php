<?php

namespace Modules\Telegram\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Telegram\Models\TelegramAccount;

/**
 * @mixin TelegramAccount
 */
class TelegramAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'phone' => $this->phone,
            'state' => $this->state,
            'tdlibBaseUrl' => $this->tdlib_base_url,
            'lastSeenAt' => $this->last_seen_at?->toIso8601String(),
            'floodWaitUntil' => $this->flood_wait_until?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
