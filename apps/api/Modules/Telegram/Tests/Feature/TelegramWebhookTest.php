<?php

namespace Modules\Telegram\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\Organization\Models\Entity;
use Spatie\Permission\Models\Role;

const WEBHOOK_TEST_SECRET = 'webhook-secret-canary';
const WEBHOOK_TEST_ORIGIN = 'https://dev.example.test';

beforeEach(function () {
    config([
        'services.telegram.bot_token' => 'test-bot-token',
        'services.telegram.bot_webhook_secret' => WEBHOOK_TEST_SECRET,
        'services.telegram.webhook_base_url' => WEBHOOK_TEST_ORIGIN,
    ]);

    foreach (['admin', 'staff'] as $role) {
        Role::findOrCreate($role, 'sanctum');
    }

    $entity = Entity::create(['code' => 'ENT-WHK', 'name' => 'Webhook Entity', 'timezone' => 'UTC', 'locale' => 'en']);

    $this->admin = User::factory()->create(['entity_id' => $entity->getKey()]);
    $this->admin->assignRole('admin');

    $this->staff = User::factory()->create(['entity_id' => $entity->getKey()]);
    $this->staff->assignRole('staff');

    $this->permissionless = User::factory()->create(['entity_id' => $entity->getKey()]);
});

/**
 * Fake getWebhookInfo separately from setWebhook so register() can report
 * the fresh registration in one round trip.
 *
 * @param  array<string, mixed>  $result
 */
function fakeWebhookInfo(array $result = []): void
{
    Http::fake([
        '*/setWebhook' => Http::response(['ok' => true, 'result' => true]),
        '*/getWebhookInfo' => Http::response([
            'ok' => true,
            'result' => array_merge([
                'url' => '',
                'pending_update_count' => 0,
            ], $result),
        ]),
    ]);
}

it('requires authentication to read or register the webhook', function () {
    $this->getJson('/api/v1/telegram/webhook')->assertStatus(401);
    $this->postJson('/api/v1/telegram/webhook')->assertStatus(401);
});

it('forbids readers without settings.view and writers without settings.manage', function () {
    fakeWebhookInfo();

    test()->actingAs($this->permissionless, 'sanctum');
    $this->getJson('/api/v1/telegram/webhook')->assertStatus(403);

    // staff holds every read — status is fine, registering is not.
    test()->actingAs($this->staff, 'sanctum');
    $this->getJson('/api/v1/telegram/webhook')->assertOk();
    $this->postJson('/api/v1/telegram/webhook')->assertStatus(403);
});

it('reports the current registration with the expected url', function () {
    fakeWebhookInfo([
        'url' => 'https://stale.example.test/api/internal/telegram/bot',
        'pending_update_count' => 3,
        'last_error_date' => 1793176375,
        'last_error_message' => 'Wrong response from the webhook: 500 Internal Server Error',
    ]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/telegram/webhook')
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['registered', 'url', 'expectedUrl', 'pendingUpdateCount', 'lastErrorAt', 'lastErrorMessage'],
        ]);

    expect($response->json('data.registered'))->toBeTrue()
        ->and($response->json('data.url'))->toBe('https://stale.example.test/api/internal/telegram/bot')
        ->and($response->json('data.expectedUrl'))->toBe(WEBHOOK_TEST_ORIGIN.'/api/internal/telegram/bot')
        ->and($response->json('data.pendingUpdateCount'))->toBe(3)
        ->and($response->json('data.lastErrorMessage'))->toContain('500 Internal Server Error');
});

it('fails closed when the bot token is not configured', function () {
    config(['services.telegram.bot_token' => null]);
    fakeWebhookInfo();

    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/telegram/webhook')
        ->assertStatus(503)
        ->assertJsonPath('message', 'Telegram bot is not configured.');

    Http::assertNothingSent();
});

it('registers the webhook with the configured origin and secret', function () {
    fakeWebhookInfo(['url' => WEBHOOK_TEST_ORIGIN.'/api/internal/telegram/bot']);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/telegram/webhook')
        ->assertOk()
        ->assertJsonPath('data.registered', true)
        ->assertJsonPath('data.url', WEBHOOK_TEST_ORIGIN.'/api/internal/telegram/bot');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/setWebhook')
            && $request['url'] === WEBHOOK_TEST_ORIGIN.'/api/internal/telegram/bot'
            && $request['secret_token'] === WEBHOOK_TEST_SECRET
            && $request['allowed_updates'] === '["message","callback_query"]';
    });

    // Write-only secret never round-trips to the caller.
    expect($response->getContent())->not->toContain(WEBHOOK_TEST_SECRET);
});

it('fails closed when the webhook secret or base url is not configured', function () {
    config(['services.telegram.bot_webhook_secret' => null]);
    fakeWebhookInfo();

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/telegram/webhook')
        ->assertStatus(503)
        ->assertJsonPath('message', 'Telegram bot webhook secret is not configured.');

    config([
        'services.telegram.bot_webhook_secret' => WEBHOOK_TEST_SECRET,
        'services.telegram.webhook_base_url' => null,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/telegram/webhook')
        ->assertStatus(503)
        ->assertJsonPath('message', 'Webhook base URL is not configured.');

    Http::assertNothingSent();
});

it('surfaces telegram rejections as a 502 envelope', function () {
    Http::fake([
        'https://api.telegram.org/*' => Http::response([
            'ok' => false,
            'error_code' => 400,
            'description' => 'Bad Request: bad webhook url',
        ], 400),
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/telegram/webhook')
        ->assertStatus(502)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId'])
        ->assertJsonPath('message', 'Bad Request: bad webhook url');
});
