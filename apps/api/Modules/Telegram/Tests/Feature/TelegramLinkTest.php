<?php

namespace Modules\Telegram\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\Organization\Models\Entity;
use Modules\Telegram\Models\TelegramLinkCode;
use Modules\Telegram\Models\TelegramMessage;

const BOT_SECRET = 'test-bot-secret';

beforeEach(function () {
    config([
        'services.telegram.bot_token' => 'test-bot-token',
        'services.telegram.bot_username' => 'ProcureBot',
        'services.telegram.bot_webhook_secret' => BOT_SECRET,
        'services.telegram.link_code_ttl_minutes' => 10,
    ]);

    $this->fakeTelegramOk = function () {
        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 555],
            ]),
        ]);
    };

    $this->entity = Entity::create(['code' => 'ENT-TGL', 'name' => 'Link Entity', 'timezone' => 'UTC', 'locale' => 'en']);
    $this->user = User::factory()->create(['entity_id' => $this->entity->getKey()]);
});

/**
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function botStartPayload(int $chatId, string $text, string $type = 'private'): array
{
    return [
        'update_id' => 42,
        'message' => [
            'message_id' => 900,
            'from' => ['id' => $chatId],
            'chat' => ['id' => $chatId, 'type' => $type],
            'text' => $text,
        ],
    ];
}

/**
 * @param  array<string, mixed>  $payload
 */
function postBotWebhook(array $payload, ?string $secret = BOT_SECRET): mixed
{
    $request = test();

    if ($secret !== null) {
        $request = $request->withHeaders(['X-Telegram-Bot-Api-Secret-Token' => $secret]);
    }

    return $request->postJson('/api/internal/telegram/bot', $payload);
}

function issueLinkCode(?User $user = null): TelegramLinkCode
{
    return TelegramLinkCode::factory()->create([
        'user_id' => ($user ?? test()->user)->getKey(),
        'entity_id' => test()->entity->getKey(),
    ]);
}

it('issues a personal deep link for the caller', function () {
    $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/telegram/link');

    $response->assertOk()
        ->assertJsonStructure(['data' => ['url', 'code', 'expiresAt']]);

    $url = (string) $response->json('data.url');
    $code = (string) $response->json('data.code');

    expect($url)->toStartWith('https://t.me/ProcureBot?start=')
        ->and($url)->toContain($code);

    $link = TelegramLinkCode::sole();

    expect($link->code)->toBe($code)
        ->and($link->user_id)->toBe($this->user->getKey())
        ->and($link->entity_id)->toBe($this->entity->getKey())
        ->and($link->created_by)->toBe($this->user->getKey())
        ->and($link->expires_at->isFuture())->toBeTrue()
        ->and($link->used_at)->toBeNull();
});

it('requires authentication to issue a link', function () {
    test()->getJson('/api/v1/telegram/link')->assertStatus(401);

    expect(TelegramLinkCode::count())->toBe(0);
});

it('fails closed when the bot username is not configured', function () {
    config(['services.telegram.bot_username' => null]);

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/telegram/link')
        ->assertStatus(503);

    expect(TelegramLinkCode::count())->toBe(0);
});

it('issues a fresh code on every request', function () {
    $first = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/telegram/link')
        ->json('data.code');
    $second = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/telegram/link')
        ->json('data.code');

    expect($first)->not->toBe($second)
        ->and(TelegramLinkCode::count())->toBe(2);
});

it('rejects a bot webhook without the secret header', function () {
    $link = issueLinkCode();

    postBotWebhook(botStartPayload(777001, "/start {$link->code}"), secret: null)
        ->assertStatus(401);

    expect($this->user->fresh()->telegram_chat_id)->toBeNull()
        ->and($link->fresh()->used_at)->toBeNull();
});

it('rejects an invalid bot webhook secret', function () {
    $link = issueLinkCode();

    postBotWebhook(botStartPayload(777001, "/start {$link->code}"), secret: 'wrong-secret')
        ->assertStatus(401);

    expect($this->user->fresh()->telegram_chat_id)->toBeNull()
        ->and($link->fresh()->used_at)->toBeNull();
});

it('fails closed when no bot webhook secret is configured', function () {
    config(['services.telegram.bot_webhook_secret' => null]);

    postBotWebhook(botStartPayload(777001, '/start anything'))->assertStatus(503);
});

it('rejects a payload that fails validation with the standard error envelope', function () {
    postBotWebhook(['message' => ['chat' => ['id' => 777001, 'type' => 'private']]])
        ->assertStatus(422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);
});

it('binds the chat to the user on a valid start code', function () {
    ($this->fakeTelegramOk)();
    $link = issueLinkCode();

    postBotWebhook(botStartPayload(777001, "/start {$link->code}"))
        ->assertOk()
        ->assertJsonPath('data.accepted', true);

    $message = TelegramMessage::sole();

    expect($this->user->fresh()->telegram_chat_id)->toBe(777001)
        ->and($link->fresh()->used_at)->not->toBeNull()
        ->and($link->fresh()->telegram_chat_id)->toBe(777001)
        ->and($message->status)->toBe(TelegramMessage::STATUS_SENT)
        ->and($message->telegram_chat_id)->toBe(777001)
        ->and($message->reference_type)->toBe('telegram_link_code')
        ->and($message->reference_id)->toBe($link->getKey())
        ->and($message->idempotency_key)->toBe("telegram-link:linked:{$link->getKey()}")
        ->and($message->body)->toContain('linked');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/bottest-bot-token/sendMessage')
            && $request['chat_id'] === 777001;
    });
});

it('accepts a start command addressed to the bot username', function () {
    ($this->fakeTelegramOk)();
    $link = issueLinkCode();

    postBotWebhook(botStartPayload(777002, "/start@ProcureBot {$link->code}"))->assertOk();

    expect($this->user->fresh()->telegram_chat_id)->toBe(777002);
});

it('ignores a bare start command with no code', function () {
    postBotWebhook(botStartPayload(777003, '/start'))->assertOk();

    expect($this->user->fresh()->telegram_chat_id)->toBeNull()
        ->and(TelegramMessage::count())->toBe(0);

    Http::assertSentCount(0);
});

it('ignores an unknown start code', function () {
    postBotWebhook(botStartPayload(777004, '/start not-a-real-code'))->assertOk();

    expect($this->user->fresh()->telegram_chat_id)->toBeNull()
        ->and(TelegramMessage::count())->toBe(0);
});

it('replies without binding when the link code has expired', function () {
    ($this->fakeTelegramOk)();
    $link = TelegramLinkCode::factory()->expired()->create([
        'user_id' => $this->user->getKey(),
        'entity_id' => $this->entity->getKey(),
    ]);

    postBotWebhook(botStartPayload(777005, "/start {$link->code}"))->assertOk();

    expect($this->user->fresh()->telegram_chat_id)->toBeNull()
        ->and($link->fresh()->used_at)->toBeNull()
        ->and(TelegramMessage::sole()->body)->toContain('expired');
});

it('tells another chat that the code was already used', function () {
    ($this->fakeTelegramOk)();
    $link = TelegramLinkCode::factory()->used()->create([
        'user_id' => $this->user->getKey(),
        'entity_id' => $this->entity->getKey(),
    ]);

    postBotWebhook(botStartPayload(888002, "/start {$link->code}"))->assertOk();

    expect($this->user->fresh()->telegram_chat_id)->toBeNull()
        ->and(TelegramMessage::sole()->body)->toContain('already been used');
});

it('rejects a chat already linked to a different account', function () {
    ($this->fakeTelegramOk)();
    $other = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $other->forceFill(['telegram_chat_id' => 777006])->save();
    $link = issueLinkCode();

    postBotWebhook(botStartPayload(777006, "/start {$link->code}"))->assertOk();

    expect($this->user->fresh()->telegram_chat_id)->toBeNull()
        ->and($link->fresh()->used_at)->toBeNull()
        ->and($other->fresh()->telegram_chat_id)->toBe(777006)
        ->and(TelegramMessage::sole()->body)->toContain('already linked to a different account');
});

it('never sends a second confirmation when the same chat redeems again', function () {
    ($this->fakeTelegramOk)();
    $link = issueLinkCode();

    postBotWebhook(botStartPayload(777007, "/start {$link->code}"))->assertOk();

    expect(TelegramMessage::count())->toBe(1);

    postBotWebhook(botStartPayload(777007, "/start {$link->code}"))->assertOk();

    expect(TelegramMessage::count())->toBe(1)
        ->and($this->user->fresh()->telegram_chat_id)->toBe(777007);
});

it('moves the link when the same user redeems a fresh code from a new chat', function () {
    ($this->fakeTelegramOk)();
    $this->user->forceFill(['telegram_chat_id' => 111001])->save();
    $link = issueLinkCode();

    postBotWebhook(botStartPayload(222002, "/start {$link->code}"))->assertOk();

    expect($this->user->fresh()->telegram_chat_id)->toBe(222002)
        ->and($link->fresh()->telegram_chat_id)->toBe(222002);
});

it('ignores start commands from group chats', function () {
    $link = issueLinkCode();

    postBotWebhook(botStartPayload(-1001234, "/start {$link->code}", 'supergroup'))->assertOk();

    expect($this->user->fresh()->telegram_chat_id)->toBeNull()
        ->and($link->fresh()->used_at)->toBeNull()
        ->and(TelegramMessage::count())->toBe(0);
});

it('binds a user without an entity but skips the confirmation reply', function () {
    $entityless = User::factory()->create(['entity_id' => null]);
    $link = TelegramLinkCode::factory()->create([
        'user_id' => $entityless->getKey(),
        'entity_id' => null,
    ]);

    postBotWebhook(botStartPayload(777008, "/start {$link->code}"))->assertOk();

    expect($entityless->fresh()->telegram_chat_id)->toBe(777008)
        ->and($link->fresh()->used_at)->not->toBeNull()
        ->and(TelegramMessage::count())->toBe(0);

    Http::assertSentCount(0);
});

it('accepts updates that carry no message', function () {
    postBotWebhook(['update_id' => 7])
        ->assertOk()
        ->assertJsonPath('data.accepted', true);

    expect(TelegramMessage::count())->toBe(0);

    Http::assertSentCount(0);
});

it('marks the reply failed when the bot API rejects it without losing the binding', function () {
    Http::fake([
        'https://api.telegram.org/*' => Http::response([
            'ok' => false,
            'error_code' => 403,
            'description' => 'Forbidden: bot was blocked by the user',
        ], 403),
    ]);
    $link = issueLinkCode();

    postBotWebhook(botStartPayload(777009, "/start {$link->code}"))->assertOk();

    expect($this->user->fresh()->telegram_chat_id)->toBe(777009)
        ->and($link->fresh()->used_at)->not->toBeNull()
        ->and(TelegramMessage::sole()->status)->toBe(TelegramMessage::STATUS_FAILED);
});
