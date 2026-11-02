<?php

namespace Modules\Telegram\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload posted by the Telegram Bot API after VerifyTelegramBotWebhook
 * has authenticated the secret_token header. Everything after update_id is
 * optional: Telegram delivers many update kinds (edited messages, callback
 * queries, channel posts) that we deliberately acknowledge with a 200
 * instead of a 422 — validation only, authorize() is never reached for
 * unsigned requests (the middleware rejects them first).
 */
class BotWebhookRequest extends FormRequest
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
            'update_id' => ['required', 'integer'],
            'message' => ['nullable', 'array'],
            'message.message_id' => ['nullable', 'integer'],
            'message.chat.id' => ['nullable', 'integer'],
            'message.chat.type' => ['nullable', 'string', 'max:32'],
            'message.from.id' => ['nullable', 'integer'],
            'message.text' => ['nullable', 'string', 'max:4000'],
            'message.reply_to_message.message_id' => ['nullable', 'integer'],
            'callback_query' => ['nullable', 'array'],
            'callback_query.id' => ['nullable', 'string', 'max:64'],
            'callback_query.chat_instance' => ['nullable', 'string', 'max:64'],
            'callback_query.from.id' => ['nullable', 'integer'],
            'callback_query.data' => ['nullable', 'string', 'max:64'],
            'callback_query.message.message_id' => ['nullable', 'integer'],
            'callback_query.message.chat.id' => ['nullable', 'integer'],
            'callback_query.message.chat.type' => ['nullable', 'string', 'max:32'],
            'callback_query.message.text' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
