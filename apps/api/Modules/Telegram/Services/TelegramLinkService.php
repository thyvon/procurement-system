<?php

namespace Modules\Telegram\Services;

use App\Models\User;
use App\Support\Context\EntityContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Telegram\Models\TelegramLinkCode;

/**
 * The /start deep-link flow behind users.telegram_chat_id.
 *
 * issue() runs in an authenticated request (EntityScope context fills
 * entity_id). handleStart() runs in the queue worker, which has no request
 * context — so the code lookup is explicitly unscoped by entity and that
 * code's entity is bound before anything is written, mirroring
 * InboundTelegramService. Redemption is claim-then-bind: an atomic
 * whereNull(used_at) update serializes concurrent taps, then the user row
 * is stamped (never mass-assigned — telegram_chat_id is not fillable).
 * Every reply rides TelegramGateway::sendBotAlert, keyed per code+outcome
 * so an at-least-once job retry can never send it twice.
 */
class TelegramLinkService
{
    /**
     * Fresh single-use code for the authenticated user; the previous ones
     * simply age out through expires_at (nothing to revoke).
     */
    public function issue(User $user): TelegramLinkCode
    {
        return TelegramLinkCode::create([
            'user_id' => $user->getKey(),
            'code' => Str::random(32),
            'expires_at' => now()->addMinutes((int) config('services.telegram.link_code_ttl_minutes', 10)),
            'created_by' => $user->getKey(),
            'updated_by' => $user->getKey(),
        ]);
    }

    /**
     * Redeem one verified Bot API webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleStart(array $payload): void
    {
        $message = $payload['message'] ?? null;

        if (! is_array($message)) {
            return;
        }

        $chatId = (int) ($message['chat']['id'] ?? 0);
        $chatType = $message['chat']['type'] ?? null;
        $text = trim((string) ($message['text'] ?? ''));

        if ($chatId === 0) {
            return;
        }

        if (is_string($chatType) && $chatType !== 'private') {
            Log::info('telegram.link.ignored', ['chat_id' => $chatId, 'reason' => 'not_private']);

            return;
        }

        $code = $this->parseStartCode($text);

        if ($code === null) {
            Log::info('telegram.link.ignored', ['chat_id' => $chatId, 'reason' => 'no_code']);

            return;
        }

        $link = TelegramLinkCode::withoutGlobalScope('entity')
            ->where('code', $code)
            ->first();

        if ($link === null) {
            Log::info('telegram.link.unknown_code', ['chat_id' => $chatId]);

            return;
        }

        app()->instance(EntityContext::class, new EntityContext($link->entity_id));

        if ($link->expires_at->isPast()) {
            $this->reply($link, $chatId, 'expired', 'This link has expired. Generate a fresh link in the app, then open it here.');

            return;
        }

        if ($link->used_at !== null) {
            $this->handleUsed($link, $chatId);

            return;
        }

        $user = User::withoutGlobalScope('entity')->find($link->user_id);

        if ($user === null) {
            Log::warning('telegram.link.user_missing', ['link_id' => $link->getKey()]);

            return;
        }

        $chatOwnedByOther = User::withoutGlobalScope('entity')
            ->where('telegram_chat_id', $chatId)
            ->where('id', '!=', $user->getKey())
            ->exists();

        if ($chatOwnedByOther) {
            $this->reply($link, $chatId, 'conflict', 'This chat is already linked to a different account.');

            return;
        }

        $claimed = TelegramLinkCode::withoutGlobalScope('entity')
            ->whereKey($link->getKey())
            ->whereNull('used_at')
            ->update([
                'used_at' => now(),
                'telegram_chat_id' => $chatId,
                'updated_at' => now(),
            ]);

        if ($claimed === 0) {
            $this->handleUsed($link->refresh(), $chatId);

            return;
        }

        if (! $this->bind($user, $chatId, $link)) {
            return;
        }

        Log::info('telegram.link.linked', [
            'link_id' => $link->getKey(),
            'user_id' => $user->getKey(),
            'chat_id' => $chatId,
        ]);

        $this->reply($link, $chatId, 'linked', 'Your account is linked to this chat. You will receive approval alerts here.');
    }

    /**
     * A code that is already redeemed: the same chat tapping again replays
     * the confirmation (same idempotency key — never a duplicate), any other
     * chat is told the code is spent. Also repairs a redeem that claimed the
     * code but crashed before stamping the user (at-least-once retry).
     */
    private function handleUsed(TelegramLinkCode $link, int $chatId): void
    {
        if ($link->telegram_chat_id !== $chatId) {
            $this->reply($link, $chatId, 'used', 'This link has already been used. Generate a fresh link in the app.');

            return;
        }

        $user = User::withoutGlobalScope('entity')->find($link->user_id);

        if ($user !== null
            && $user->telegram_chat_id !== $chatId
            && ! $this->bind($user, $chatId, $link)) {
            return;
        }

        $this->reply($link, $chatId, 'linked', 'Your account is linked to this chat. You will receive approval alerts here.');
    }

    /**
     * Stamp the chat onto the user row. telegram_chat_id is deliberately
     * not fillable, hence forceFill; the unique index is the last line of
     * defense if two chats pass the ownership check concurrently.
     */
    private function bind(User $user, int $chatId, TelegramLinkCode $link): bool
    {
        try {
            $user->forceFill(['telegram_chat_id' => $chatId])->save();
        } catch (QueryException) {
            Log::warning('telegram.link.bind_race', [
                'link_id' => $link->getKey(),
                'user_id' => $user->getKey(),
                'chat_id' => $chatId,
            ]);

            $this->reply($link, $chatId, 'conflict', 'This chat is already linked to a different account.');

            return false;
        }

        return true;
    }

    /**
     * One Bot API reply per code+outcome. Guarded exactly like
     * TelegramAlertChannel: without a bot token or an entity there is no
     * audit row to hang the send on (telegram_messages.entity_id is NOT
     * NULL), so the reply is logged and dropped instead.
     *
     * @param  'linked'|'expired'|'used'|'conflict'  $kind
     */
    private function reply(TelegramLinkCode $link, int $chatId, string $kind, string $body): void
    {
        if (blank(config('services.telegram.bot_token'))) {
            Log::warning('telegram.link.reply_skipped', [
                'link_id' => $link->getKey(),
                'reason' => 'bot_token_missing',
            ]);

            return;
        }

        if (blank($link->entity_id)) {
            Log::warning('telegram.link.reply_skipped', [
                'link_id' => $link->getKey(),
                'reason' => 'missing_entity',
            ]);

            return;
        }

        app(TelegramGateway::class)->sendBotAlert(
            chatId: $chatId,
            body: $body,
            idempotencyKey: "telegram-link:{$kind}:{$link->getKey()}",
            entityId: (string) $link->entity_id,
            reference: [
                'reference_type' => 'telegram_link_code',
                'reference_id' => $link->getKey(),
            ],
        );
    }

    /**
     * "/start <code>" or "/start@botname <code>" → the code; a bare
     * "/start" (user tapped the bot's Start button) or anything else → null.
     */
    private function parseStartCode(string $text): ?string
    {
        if (preg_match('/^\/start(?:@\w+)?(?:\s+([A-Za-z0-9_-]{1,64}))?$/D', $text, $matches) !== 1) {
            return null;
        }

        return $matches[1] ?? null;
    }
}
