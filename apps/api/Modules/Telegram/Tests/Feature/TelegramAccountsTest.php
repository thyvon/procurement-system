<?php

namespace Modules\Telegram\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\Organization\Models\Entity;
use Modules\Telegram\Models\TelegramAccount;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'staff'] as $role) {
        Role::findOrCreate($role, 'sanctum');
    }

    $this->entity = Entity::create(['code' => 'ENT-TGA', 'name' => 'Telegram A', 'timezone' => 'UTC', 'locale' => 'en']);
    $this->otherEntity = Entity::create(['code' => 'ENT-TGB', 'name' => 'Telegram B', 'timezone' => 'UTC', 'locale' => 'en']);

    $this->admin = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->admin->assignRole('admin');

    $this->staff = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->staff->assignRole('staff');
});

it('creates an account as admin with a disconnected state', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/telegram/accounts', [
            'label' => 'Supplier Line A',
            'phone' => '+85512345678',
            'tdlib_base_url' => 'http://tdlib-a:3080',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.label', 'Supplier Line A')
        ->assertJsonPath('data.state', 'disconnected')
        ->assertJsonPath('data.tdlibBaseUrl', 'http://tdlib-a:3080');

    expect($response->json('data.id'))->toBeString();
});

it('forbids staff from creating accounts', function () {
    $this->actingAs($this->staff, 'sanctum')
        ->postJson('/api/v1/telegram/accounts', ['label' => 'Nope', 'phone' => '+8551234567'])
        ->assertStatus(403);
});

it('requires authentication for the account list', function () {
    $this->getJson('/api/v1/telegram/accounts')->assertStatus(401);
});

it('rejects an invalid phone with the standard error envelope', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/telegram/accounts', ['label' => 'Bad', 'phone' => 'abc'])
        ->assertStatus(422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);
});

it('lists only accounts of the caller entity', function () {
    TelegramAccount::factory()->create(['entity_id' => $this->entity->getKey(), 'label' => 'Own Line']);
    TelegramAccount::factory()->create(['entity_id' => $this->otherEntity->getKey(), 'label' => 'Foreign Line']);

    $labels = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/telegram/accounts')
        ->assertOk()
        ->json('data.*.label');

    expect($labels)->toContain('Own Line')->not->toContain('Foreign Line');
});

it('hides accounts of other entities behind route model binding', function () {
    $foreign = TelegramAccount::factory()->create(['entity_id' => $this->otherEntity->getKey()]);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/telegram/accounts/{$foreign->id}")
        ->assertStatus(404);
});

it('updates the label as admin', function () {
    $account = TelegramAccount::factory()->create(['entity_id' => $this->entity->getKey()]);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/v1/telegram/accounts/{$account->id}", ['label' => 'Renamed Line'])
        ->assertOk()
        ->assertJsonPath('data.label', 'Renamed Line');
});

it('forwards the login code to the tdlib service with the service token', function () {
    Http::fake(['http://tdlib.test/*' => Http::response(['state' => 'ready'])]);

    $account = TelegramAccount::factory()
        ->waitingCode()
        ->create(['entity_id' => $this->entity->getKey()]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/telegram/accounts/{$account->id}/login-code", ['code' => '12345'])
        ->assertOk()
        ->assertJsonPath('data.state', 'ready');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/auth/code')
            && $request->hasHeader('Authorization', 'Bearer test-service-token');
    });
});

it('forbids staff from entering a login code', function () {
    $account = TelegramAccount::factory()->create(['entity_id' => $this->entity->getKey()]);

    $this->actingAs($this->staff, 'sanctum')
        ->postJson("/api/v1/telegram/accounts/{$account->id}/login-code", ['code' => '12345'])
        ->assertStatus(403);
});

it('mirrors the pod health state back onto the account', function () {
    Http::fake(['http://tdlib.test/*' => Http::response(['state' => 'ready', 'connection' => 'ready'])]);

    $account = TelegramAccount::factory()
        ->waitingCode()
        ->create(['entity_id' => $this->entity->getKey()]);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson("/api/v1/telegram/accounts/{$account->id}/health")
        ->assertOk()
        ->assertJsonPath('data.state', 'ready');

    expect($account->fresh()->state)->toBe('ready');
});
