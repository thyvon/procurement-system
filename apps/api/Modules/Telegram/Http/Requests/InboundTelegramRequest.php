<?php

namespace Modules\Telegram\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Payload posted by the TDLib service after VerifyTelegramWebhook has
 * authenticated the HMAC signature. Validation only — authorize() is never
 * reached for unsigned requests (the middleware rejects them first).
 */
class InboundTelegramRequest extends FormRequest
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
            'account_id' => [
                'required',
                'ulid',
                Rule::exists('telegram_accounts', 'id')->whereNull('deleted_at'),
            ],
            'chat_id' => ['required', 'integer'],
            'message_id' => ['required', 'integer'],
            'text' => ['nullable', 'string', 'max:4000'],
            'from_user_id' => ['nullable', 'integer'],
            'sent_at' => ['nullable', 'date'],
        ];
    }
}
