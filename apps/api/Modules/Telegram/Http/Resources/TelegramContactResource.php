<?php

namespace Modules\Telegram\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Telegram\Models\TelegramContact;

/**
 * @mixin TelegramContact
 */
class TelegramContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'telegramUserId' => $this->telegram_user_id,
            'telegramChatId' => $this->telegram_chat_id,
            'telegramAccountId' => $this->telegram_account_id,
            'language' => $this->language,
            'supplierCode' => $this->supplier_code,
            'verifiedAt' => $this->verified_at?->toIso8601String(),
        ];
    }
}
