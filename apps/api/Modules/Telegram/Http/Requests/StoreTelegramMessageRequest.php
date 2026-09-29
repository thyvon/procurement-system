<?php

namespace Modules\Telegram\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTelegramMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'telegram_contact_id' => [
                'required',
                Rule::exists('telegram_contacts', 'id')
                    ->where('entity_id', $this->user()?->entity_id)
                    ->whereNull('deleted_at'),
            ],
            'body' => ['required', 'string', 'max:4000'],
            'idempotency_key' => ['sometimes', 'string', 'max:128'],
        ];
    }
}
