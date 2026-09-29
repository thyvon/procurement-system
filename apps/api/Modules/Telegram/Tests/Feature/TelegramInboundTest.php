<?php

namespace Modules\Telegram\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Event;
use Modules\Organization\Models\Entity;
use Modules\Telegram\Events\TelegramMessageReceived;
use Modules\Telegram\Models\TelegramAccount;
use Modules\Telegram\Models\TelegramContact;
use Modules\Telegram\Models\TelegramMessage;
use Spatie\Permission\Models\Role;

const WEBHOOK_SECRET = 'test-webhook-secret';

beforeEach(function () {
    foreach (['admin', 'staff'] as $role) {
        Role::findOrCreate($role, 'sanctum');
    }

    $this->entity = Entity::create(['code' => 'ENT-TGI', 'name' => 'Inbound Entity', 'timezone' => 'UTC', 'locale' => 'en']);
    $this->otherEntity = Entity::create(['code' => 'ENT-TG3', 'name' => 'Third Entity', 'timezone' => 'UTC', 'locale' => 'en']);

    $this->admin = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->admin->assignRole('admin');

    $this->account = TelegramAccount::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->contact = TelegramContact::factory()->withChat(555001)->create([
        'entity_id' => $this->entity->getKey(),
        'telegram_account_id' => $this->account->id,
    ]);
});

/**
 * Signs the raw JSON body exactly like the TDLib service does:
 * HMAC-SHA256 over "{timestamp}.{body}".
 *
 * @param  array<string, mixed>  $payload
 * @return array{0: array<string, string>, 1: string}
 */
function signedWebhook(array $payload, ?string $timestamp = null, ?string $signature = null): array
{
    $body = json_encode($payload);
    $timestamp ??= (string) time();
    $signature ??= hash_hmac('sha256', $timestamp.'.'.$body, WEBHOOK_SECRET);

    return [
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_TELEGRAM_TIMESTAMP' => $timestamp,
            'HTTP_X_TELEGRAM_SIGNATURE' => $signature,
        ],
        $body,
    ];
}

/**
 * @param  array<string, mixed>  $payload
 */
function postWebhook(array $payload, ?string $timestamp = null, ?string $signature = null): mixed
{
    [$server, $body] = signedWebhook($payload, $timestamp, $signature);

    return test()->call('POST', '/api/internal/telegram/inbound', server: $server, content: $body);
}

it('accepts a correctly signed inbound message and stores it under the contact entity', function () {
    Event::fake([TelegramMessageReceived::class]);

    $response = postWebhook([
        'account_id' => $this->account->id,
        'chat_id' => 555001,
        'message_id' => 11,
        'text' => 'RFQ-2041 price 2.8 USD per ream delivery 3 days',
    ]);

    $response->assertOk()->assertJsonPath('data.accepted', true);

    $message = TelegramMessage::sole();

    expect($message->direction)->toBe('in')
        ->and($message->status)->toBe('received')
        ->and($message->entity_id)->toBe($this->entity->getKey())
        ->and($message->telegram_contact_id)->toBe($this->contact->id)
        ->and($message->telegram_message_id)->toBe(11)
        ->and($message->body)->toContain('RFQ-2041');

    Event::assertDispatched(TelegramMessageReceived::class);
});

it('rejects unsigned requests', function () {
    test()->postJson('/api/internal/telegram/inbound', ['account_id' => $this->account->id])
        ->assertStatus(401);
});

it('rejects an invalid signature', function () {
    postWebhook(
        ['account_id' => $this->account->id, 'chat_id' => 555001, 'message_id' => 12, 'text' => 'hi'],
        signature: str_repeat('0', 64),
    )->assertStatus(401);

    expect(TelegramMessage::count())->toBe(0);
});

it('rejects a timestamp outside the replay window', function () {
    postWebhook(
        ['account_id' => $this->account->id, 'chat_id' => 555001, 'message_id' => 13, 'text' => 'hi'],
        timestamp: (string) (time() - 400),
    )->assertStatus(401);

    expect(TelegramMessage::count())->toBe(0);
});

/**
 * Cross-language parity fixture — the SAME assertion exists in
 * apps/tdlib-service/test/signature.test.js. If either side changes the
 * signing format, one of the two suites fails.
 */
it('matches the cross-language signature fixture shared with the TDLib service', function () {
    $body = '{"account_id":"01ARZ3NDEKTSV4RRFFQ69G5FAV","chat_id":1,"message_id":1,"text":"hi"}';
    $timestamp = '1700000000';

    $signature = hash_hmac('sha256', $timestamp.'.'.$body, WEBHOOK_SECRET);

    expect($signature)->toBe('c55564bf3c84b7970b6e2b11d2f48fbd30e3750a28ec49e14c3065a1c1eca02b');
});

it('rejects a payload that fails validation with the standard error envelope', function () {
    postWebhook(['account_id' => $this->account->id, 'text' => 'missing ids'])
        ->assertStatus(422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);

    expect(TelegramMessage::count())->toBe(0);
});

it('ignores duplicate deliveries of the same telegram message', function () {
    Event::fake([TelegramMessageReceived::class]);

    $payload = ['account_id' => $this->account->id, 'chat_id' => 555001, 'message_id' => 21, 'text' => 'once'];

    postWebhook($payload)->assertOk();
    postWebhook($payload)->assertOk();

    expect(TelegramMessage::count())->toBe(1);
    Event::assertDispatchedTimes(TelegramMessageReceived::class, 1);
});

it('logs and drops unknown senders', function () {
    Event::fake([TelegramMessageReceived::class]);

    postWebhook([
        'account_id' => $this->account->id,
        'chat_id' => 999888,
        'message_id' => 31,
        'text' => 'stranger',
    ])->assertOk();

    expect(TelegramMessage::count())->toBe(0);
    Event::assertNotDispatched(TelegramMessageReceived::class);
});

it('stores a message under the contact entity even though the worker has no entity context', function () {
    Event::fake([TelegramMessageReceived::class]);

    $foreignAccount = TelegramAccount::factory()->create(['entity_id' => $this->otherEntity->getKey()]);
    $foreignContact = TelegramContact::factory()->withChat(777001)->create([
        'entity_id' => $this->otherEntity->getKey(),
        'telegram_account_id' => $foreignAccount->id,
    ]);

    postWebhook([
        'account_id' => $foreignAccount->id,
        'chat_id' => 777001,
        'message_id' => 41,
        'text' => 'supplier B replying',
    ])->assertOk();

    $message = TelegramMessage::sole();

    expect($message->entity_id)->toBe($this->otherEntity->getKey())
        ->and($message->telegram_contact_id)->toBe($foreignContact->id);

    Event::assertDispatched(TelegramMessageReceived::class, fn (TelegramMessageReceived $event): bool => $event->message->id === $message->id);
});

it('flags media-only messages without text for review', function () {
    postWebhook([
        'account_id' => $this->account->id,
        'chat_id' => 555001,
        'message_id' => 51,
    ])->assertOk();

    expect(TelegramMessage::sole()->status)->toBe('needs_review');
});

it('touches last_seen_at on the account when a message arrives', function () {
    $this->account->update(['last_seen_at' => null]);

    postWebhook([
        'account_id' => $this->account->id,
        'chat_id' => 555001,
        'message_id' => 61,
        'text' => 'hello',
    ])->assertOk();

    expect($this->account->fresh()->last_seen_at)->not->toBeNull();
});
