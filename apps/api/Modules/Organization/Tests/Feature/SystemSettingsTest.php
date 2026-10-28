<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Modules\Organization\Models\Entity;
use Modules\Organization\Models\SystemSetting;
use Modules\Organization\Services\SystemSettingsService;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'staff'] as $role) {
        Role::findOrCreate($role, 'sanctum');
    }

    $entity = Entity::create(['code' => 'ENT-SET', 'name' => 'Settings Entity', 'timezone' => 'UTC', 'locale' => 'en']);

    $this->admin = User::factory()->create(['entity_id' => $entity->getKey()]);
    $this->admin->assignRole('admin');

    $this->staff = User::factory()->create(['entity_id' => $entity->getKey()]);
    $this->staff->assignRole('staff');

    $this->permissionless = User::factory()->create(['entity_id' => $entity->getKey()]);

    config()->set('services.telegram.bot_username', 'EnvBot');
    config()->set('services.telegram.bot_token', 'env-token-canary');
    config()->set('services.telegram.alerts_enabled', true);
    config()->set('services.telegram.alert_rate_per_minute', 30);
});

function systemSettingItem(TestResponse $response, string $key): array
{
    return collect($response->json('data.items'))->firstWhere('key', $key);
}

it('requires authentication for read and write', function () {
    $this->getJson('/api/v1/system-settings/telegram')->assertStatus(401);
    $this->putJson('/api/v1/system-settings/telegram', ['values' => ['bot_username' => 'X']])->assertStatus(401);
});

it('forbids readers without the settings.view ability', function () {
    test()->actingAs($this->permissionless, 'sanctum');

    $this->getJson('/api/v1/system-settings/telegram')->assertStatus(403);
});

it('returns 404 for an unknown group', function () {
    test()->actingAs($this->admin, 'sanctum');

    $this->getJson('/api/v1/system-settings/not-a-group')->assertStatus(404);
    $this->putJson('/api/v1/system-settings/not-a-group', ['values' => ['bot_username' => 'X']])->assertStatus(404);
});

it('returns the telegram settings with environment fallbacks for staff readers', function () {
    test()->actingAs($this->staff, 'sanctum');

    $response = $this->getJson('/api/v1/system-settings/telegram')->assertOk()
        ->assertJsonPath('data.group', 'telegram')
        ->assertJsonPath('data.label', 'Telegram integration');

    expect($response->json('data.items'))->toHaveCount(11);

    $username = systemSettingItem($response, 'bot_username');
    expect($username['value'])->toBe('EnvBot')
        ->and($username['configured'])->toBeFalse()
        ->and($username['secret'])->toBeFalse()
        ->and($username['env'])->toBe('TELEGRAM_BOT_USERNAME');

    $rate = systemSettingItem($response, 'alert_rate_per_minute');
    expect($rate['value'])->toBe(30)
        ->and($rate['min'])->toBe(1)
        ->and($rate['max'])->toBe(600);

    $enabled = systemSettingItem($response, 'alerts_enabled');
    expect($enabled['value'])->toBeTrue();
});

it('never serializes secret values from either source', function () {
    test()->actingAs($this->staff, 'sanctum');

    $fallback = $this->getJson('/api/v1/system-settings/telegram')->assertOk();
    expect(systemSettingItem($fallback, 'bot_token')['value'])->toBeNull()
        ->and(systemSettingItem($fallback, 'bot_token')['secret'])->toBeTrue();
    expect($fallback->getContent())->not->toContain('env-token-canary');

    test()->actingAs($this->admin, 'sanctum');
    $this->putJson('/api/v1/system-settings/telegram', ['values' => ['bot_token' => 'stored-secret-canary']])
        ->assertOk();

    $override = $this->getJson('/api/v1/system-settings/telegram')->assertOk();
    expect(systemSettingItem($override, 'bot_token')['value'])->toBeNull()
        ->and(systemSettingItem($override, 'bot_token')['configured'])->toBeTrue();
    expect($override->getContent())->not->toContain('stored-secret-canary');
});

it('stores overrides encrypted at rest', function () {
    test()->actingAs($this->admin, 'sanctum');

    $this->putJson('/api/v1/system-settings/telegram', ['values' => ['bot_token' => 'plain-canary-123']])
        ->assertOk();

    $raw = DB::table('system_settings')->where('key', 'bot_token')->value('value');

    expect($raw)->not->toBeNull()
        ->and($raw)->not->toContain('plain-canary-123');
});

it('persists overrides and applies them to config in the same request', function () {
    test()->actingAs($this->admin, 'sanctum');

    $response = $this->putJson('/api/v1/system-settings/telegram', ['values' => [
        'bot_username' => 'NewBot',
        'alerts_enabled' => false,
        'alert_rate_per_minute' => 45,
    ]])->assertOk()
        ->assertJsonPath('data.group', 'telegram');

    expect(systemSettingItem($response, 'bot_username')['value'])->toBe('NewBot')
        ->and(systemSettingItem($response, 'bot_username')['configured'])->toBeTrue()
        ->and(systemSettingItem($response, 'alerts_enabled')['value'])->toBeFalse();

    expect(config('services.telegram.bot_username'))->toBe('NewBot')
        ->and(config('services.telegram.alerts_enabled'))->toBeFalse()
        ->and(config('services.telegram.alert_rate_per_minute'))->toBe(45);

    expect(SystemSetting::query()->where('key', 'bot_username')->count())->toBe(1);
});

it('records who changed a setting', function () {
    test()->actingAs($this->admin, 'sanctum');

    $this->putJson('/api/v1/system-settings/telegram', ['values' => ['bot_username' => 'Audited']])->assertOk();

    $row = SystemSetting::query()->where('key', 'bot_username')->sole();

    expect($row->created_by)->toBe($this->admin->getKey())
        ->and($row->updated_by)->toBe($this->admin->getKey());
});

it('rejects keys outside the whitelist', function () {
    test()->actingAs($this->admin, 'sanctum');

    $this->putJson('/api/v1/system-settings/telegram', ['values' => ['app.debug' => '1']])
        ->assertStatus(422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);

    expect(SystemSetting::query()->count())->toBe(0);
});

it('rejects invalid types and out-of-range integers', function () {
    test()->actingAs($this->admin, 'sanctum');

    $this->putJson('/api/v1/system-settings/telegram', ['values' => ['alert_rate_per_minute' => 'abc']])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['values.alert_rate_per_minute']);

    $this->putJson('/api/v1/system-settings/telegram', ['values' => ['alert_rate_per_minute' => 0]])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['values.alert_rate_per_minute']);

    $this->putJson('/api/v1/system-settings/telegram', ['values' => ['alerts_enabled' => 'maybe']])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['values.alerts_enabled']);

    $this->putJson('/api/v1/system-settings/telegram', ['values' => ['base_url' => 'javascript:alert(1)']])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['values.base_url']);

    expect(SystemSetting::query()->count())->toBe(0);
});

it('clears an override with null and falls back to the environment', function () {
    test()->actingAs($this->admin, 'sanctum');

    $this->putJson('/api/v1/system-settings/telegram', ['values' => ['bot_username' => 'NewBot']])->assertOk();
    expect(config('services.telegram.bot_username'))->toBe('NewBot');

    $response = $this->putJson('/api/v1/system-settings/telegram', ['values' => ['bot_username' => null]])
        ->assertOk();

    expect(systemSettingItem($response, 'bot_username')['value'])->toBe('EnvBot')
        ->and(systemSettingItem($response, 'bot_username')['configured'])->toBeFalse()
        ->and(config('services.telegram.bot_username'))->toBe('EnvBot')
        ->and(SystemSetting::query()->where('key', 'bot_username')->sole()->value)->toBeNull();
});

it('treats an empty string as clearing a secret', function () {
    test()->actingAs($this->admin, 'sanctum');

    $this->putJson('/api/v1/system-settings/telegram', ['values' => ['bot_token' => 'new-token']])->assertOk();
    expect(config('services.telegram.bot_token'))->toBe('new-token');

    $response = $this->putJson('/api/v1/system-settings/telegram', ['values' => ['bot_token' => '']])
        ->assertOk();

    expect(systemSettingItem($response, 'bot_token')['configured'])->toBeFalse()
        ->and(config('services.telegram.bot_token'))->toBe('env-token-canary');
});

it('forbids staff from writing settings', function () {
    test()->actingAs($this->staff, 'sanctum');

    $this->putJson('/api/v1/system-settings/telegram', ['values' => ['bot_username' => 'Hacked']])
        ->assertStatus(403);

    expect(SystemSetting::query()->count())->toBe(0);
});

it('applies persisted overrides through the config overlay', function () {
    SystemSetting::factory()->create([
        'group' => 'telegram',
        'key' => 'rate_per_minute',
        'value' => '9',
    ]);

    app(SystemSettingsService::class)->overlayConfig();

    expect(config('services.telegram.rate_per_minute'))->toBe(9);
});

it('ignores database rows that are not in the whitelist', function () {
    SystemSetting::factory()->create([
        'key' => 'bot_username',
        'value' => 'ok',
    ]);
    SystemSetting::query()->forceCreate([
        'group' => 'evil',
        'key' => 'app.key',
        'value' => 'compromised',
    ]);

    app(SystemSettingsService::class)->overlayConfig();

    expect(config('app.key'))->not->toBe('compromised');
});
