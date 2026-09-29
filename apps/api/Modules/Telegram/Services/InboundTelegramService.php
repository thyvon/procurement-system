<?php

namespace Modules\Telegram\Services;

use App\Support\Context\EntityContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Modules\Telegram\Events\TelegramMessageReceived;
use Modules\Telegram\Models\TelegramAccount;
use Modules\Telegram\Models\TelegramContact;
use Modules\Telegram\Models\TelegramMessage;

/**
 * Turns a verified webhook payload into stored inbound messages.
 *
 * The queue worker has no EntityScope context, so entity resolution is
 * explicit: the contact is matched on the globally-unique telegram_chat_id
 * WITHOUT the entity scope, and that contact's entity is bound before
 * anything is written. Unknown senders are logged and dropped.
 */
class InboundTelegramService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $accountId = (string) $payload['account_id'];
        $chatId = (int) $payload['chat_id'];
        $messageId = (int) $payload['message_id'];

        $duplicate = TelegramMessage::withoutGlobalScope('entity')
            ->where('telegram_account_id', $accountId)
            ->where('telegram_chat_id', $chatId)
            ->where('telegram_message_id', $messageId)
            ->exists();

        if ($duplicate) {
            Log::info('telegram.inbound.duplicate', [
                'account_id' => $accountId,
                'chat_id' => $chatId,
                'telegram_message_id' => $messageId,
            ]);

            return;
        }

        $contact = TelegramContact::withoutGlobalScope('entity')
            ->where('telegram_chat_id', $chatId)
            ->first();

        if ($contact === null) {
            Log::info('telegram.inbound.unknown_sender', [
                'account_id' => $accountId,
                'chat_id' => $chatId,
            ]);

            return;
        }

        app()->instance(EntityContext::class, new EntityContext($contact->entity_id));

        $body = trim((string) ($payload['text'] ?? ''));

        try {
            $message = TelegramMessage::create([
                'entity_id' => $contact->entity_id,
                'direction' => TelegramMessage::DIRECTION_IN,
                'telegram_account_id' => $accountId,
                'telegram_contact_id' => $contact->id,
                'telegram_chat_id' => $chatId,
                'telegram_message_id' => $messageId,
                'body' => $body,
                'status' => $body === ''
                    ? TelegramMessage::STATUS_NEEDS_REVIEW
                    : TelegramMessage::STATUS_RECEIVED,
                'idempotency_key' => "in:{$accountId}:{$chatId}:{$messageId}",
                'created_by' => null,
                'updated_by' => null,
            ]);
        } catch (QueryException) {
            // Unique-key race: another worker stored the same Telegram message.
            Log::info('telegram.inbound.duplicate_race', [
                'account_id' => $accountId,
                'chat_id' => $chatId,
                'telegram_message_id' => $messageId,
            ]);

            return;
        }

        $account = TelegramAccount::withoutGlobalScope('entity')->find($accountId);
        $account?->update(['last_seen_at' => now()]);

        Log::info('telegram.inbound.received', [
            'account_id' => $accountId,
            'chat_id' => $chatId,
            'contact_id' => $contact->id,
            'message_id' => $message->id,
        ]);

        event(new TelegramMessageReceived($message));
    }
}
