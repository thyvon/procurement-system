<?php

namespace Modules\Telegram\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTelegramContactRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            // At least one of phone / username is required to identify the
            // Telegram account; username-only contacts skip the phone upload.
            'phone' => ['required_without:username', 'nullable', 'string', 'max:32', 'regex:/^\+?[0-9]{6,15}$/'],
            'username' => ['required_without:phone', 'nullable', 'string', 'max:32', 'regex:/^@[A-Za-z0-9_]{5,32}$/'],
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
