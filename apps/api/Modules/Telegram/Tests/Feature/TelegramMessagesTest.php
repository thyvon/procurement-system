<?php

namespace Modules\Telegram\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\Organization\Models\Entity;
use Modules\Telegram\Models\TelegramAccount;
use Modules\Telegram\Models\TelegramContact;
use Modules\Telegram\Models\TelegramMessage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'staff'] as $role) {
        Role::findOrCreate($role, 'sanctum');
    }

    $this->entity = Entity::create(['code' => 'ENT-TGM', 'name' => 'Messages Entity', 'timezone' => 'UTC', 'locale' => 'en']);
    $this->otherEntity = Entity::create(['code' => 'ENT-TG2', 'name' => 'Other Entity', 'timezone' => 'UTC', 'locale' => 'en']);

    $this->admin = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->admin->assignRole('admin');

    $this->staff = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->staff->assignRole('staff');

    $this->account = TelegramAccount::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->contact = TelegramContact::factory()->withChat(424242)->create([
        'entity_id' => $this->entity->getKey(),
        'telegram_account_id' => $this->account->id,
    ]);
});

it('sends a message to a contact and marks it sent', function () {
    Http::fake([
        'http://tdlib.test/*' => Http::response(['ok' => true, 'message_id' => 999, 'chat_id' => 424242]),
    ]);

    $expectedPhone = $this->contact->phone;
    $expectedBody = 'RFQ-2041: please quote 200 reams of A4 paper.';

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/telegram/messages', [
            'telegram_contact_id' => $this->contact->id,
            'body' => $expectedBody,
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.status', 'sent')
        ->assertJsonPath('data.direction', 'out')
        ->assertJsonPath('data.telegramMessageId', 999);

    Http::assertSent(function ($request) use ($expectedPhone, $expectedBody) {
        return str_ends_with($request->url(), '/send')
            && $request['phone'] === $expectedPhone
            && $request['text'] === $expectedBody
            && $request->hasHeader('Authorization', 'Bearer test-service-token');
    });

    expect($response->json('data.id'))->toBeString();
});

it('sends to a username-only contact resolved by the pod', function () {
    Http::fake([
        'http://tdlib.test/*' => Http::response(['ok' => true, 'message_id' => 555, 'chat_id' => 777001]),
    ]);

    $contact = TelegramContact::factory()->withUsername('@vunthypro')->create([
        'entity_id' => $this->entity->getKey(),
        'telegram_account_id' => $this->account->id,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/telegram/messages', [
            'telegram_contact_id' => $contact->id,
            'body' => 'Username-only send test',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.status', 'sent')
        ->assertJsonPath('data.telegramChatId', 777001);

    Http::assertSent(function ($request) {
        return $request['username'] === '@vunthypro'
            && $request['phone'] === null
            && $request['chat_id'] === null;
    });

    expect($contact->refresh()->telegram_chat_id)->toBe(777001);
});

it('replays an idempotency key without sending twice', function () {
    Http::fake([
        'http://tdlib.test/*' => Http::response(['ok' => true, 'message_id' => 1, 'chat_id' => 424242]),
    ]);

    $payload = [
        'telegram_contact_id' => $this->contact->id,
        'body' => 'PO-1024 confirmed?',
        'idempotency_key' => 'po-1024-confirm',
    ];

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/telegram/messages', $payload)
        ->assertStatus(201);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/telegram/messages', $payload)
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'sent');

    expect(TelegramMessage::count())->toBe(1);
    Http::assertSentCount(1);
});

it('rejects sending when the account is not ready', function () {
    $account = TelegramAccount::factory()->waitingCode()->create(['entity_id' => $this->entity->getKey()]);
    $contact = TelegramContact::factory()->create([
        'entity_id' => $this->entity->getKey(),
        'telegram_account_id' => $account->id,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/telegram/messages', [
            'telegram_contact_id' => $contact->id,
            'body' => 'Hello?',
        ])
        ->assertStatus(422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId']);

    expect(TelegramMessage::count())->toBe(0);
});

it('marks the account throttled and the message queued on flood-wait', function () {
    Http::fake([
        'http://tdlib.test/*' => Http::response(['error' => 'FLOOD_WAIT', 'retryAfter' => 120], 429),
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/telegram/messages', [
            'telegram_contact_id' => $this->contact->id,
            'body' => 'Flood test',
        ])
        ->assertStatus(201);

    $freshAccount = $this->account->fresh();

    expect($freshAccount->state)->toBe('throttled')
        ->and($freshAccount->flood_wait_until)->not->toBeNull()
        ->and(TelegramMessage::first()->status)->toBe('queued');
});

it('marks the message failed when the pod rejects it permanently', function () {
    Http::fake([
        'http://tdlib.test/*' => Http::response(['error' => 'PHONE_NUMBER_BANNED'], 400),
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/telegram/messages', [
            'telegram_contact_id' => $this->contact->id,
            'body' => 'Banned test',
        ])
        ->assertStatus(201);

    $message = TelegramMessage::first();

    expect($message->status)->toBe('failed')
        ->and($message->error)->toContain('PHONE_NUMBER_BANNED');
});

it('lets staff read the conversation history of their own contact only', function () {
    TelegramMessage::factory()->create([
        'entity_id' => $this->entity->getKey(),
        'telegram_account_id' => $this->account->id,
        'telegram_contact_id' => $this->contact->id,
        'body' => 'Own message',
    ]);

    $foreignAccount = TelegramAccount::factory()->create(['entity_id' => $this->otherEntity->getKey()]);
    $foreignContact = TelegramContact::factory()->create([
        'entity_id' => $this->otherEntity->getKey(),
        'telegram_account_id' => $foreignAccount->id,
    ]);
    TelegramMessage::factory()->create([
        'entity_id' => $this->otherEntity->getKey(),
        'telegram_account_id' => $foreignAccount->id,
        'telegram_contact_id' => $foreignContact->id,
        'body' => 'Foreign message',
    ]);

    $bodies = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/telegram/messages?telegram_contact_id='.$this->contact->id)
        ->assertOk()
        ->json('data.*.body');

    expect($bodies)->toContain('Own message')->not->toContain('Foreign message');

    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/telegram/messages?telegram_contact_id='.$foreignContact->id)
        ->assertStatus(422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);
});

it('forbids staff from sending messages', function () {
    $this->actingAs($this->staff, 'sanctum')
        ->postJson('/api/v1/telegram/messages', [
            'telegram_contact_id' => $this->contact->id,
            'body' => 'Not allowed',
        ])
        ->assertStatus(403);
});
