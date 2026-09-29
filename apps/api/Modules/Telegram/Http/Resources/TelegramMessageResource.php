<?php

namespace Modules\Telegram\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Telegram\Models\TelegramMessage;

/**
 * @mixin TelegramMessage
 */
class TelegramMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'direction' => $this->direction,
            'telegramAccountId' => $this->telegram_account_id,
            'telegramContactId' => $this->telegram_contact_id,
            'telegramChatId' => $this->telegram_chat_id,
            'telegramMessageId' => $this->telegram_message_id,
            'referenceType' => $this->reference_type,
            'referenceId' => $this->reference_id,
            'body' => $this->body,
            'filePath' => $this->file_path,
            'status' => $this->status,
            'parsedJson' => $this->parsed_json,
            'error' => $this->error,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
