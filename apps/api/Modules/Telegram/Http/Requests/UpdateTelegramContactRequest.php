<?php

namespace Modules\Telegram\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTelegramContactRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32', 'regex:/^\+?[0-9]{6,15}$/'],
            'username' => ['sometimes', 'nullable', 'string', 'max:32', 'regex:/^@[A-Za-z0-9_]{5,32}$/'],
            'language' => ['sometimes', Rule::in(['km', 'en'])],
            'supplier_code' => ['nullable', 'string', 'max:64'],
            'telegram_account_id' => [
                'nullable',
                Rule::exists('telegram_accounts', 'id')
                    ->where('entity_id', $this->user()?->entity_id)
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
