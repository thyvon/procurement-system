<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Auth\Models\RefreshToken;

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'admin@procurement.local',
        'password' => 'secret-password',
    ]);
});

function loginPayload(): array
{
    $response = test()->postJson('/api/v1/auth/login', [
        'email' => 'admin@procurement.local',
        'password' => 'secret-password',
    ]);

    $response->assertOk();

    return $response->json('data');
}

it('logs in with valid credentials and returns token pair', function () {
    $data = loginPayload();

    expect($data['user']['email'])->toBe('admin@procurement.local')
        ->and($data['access_token'])->not->toBeEmpty()
        ->and($data['refresh_token'])->not->toBeEmpty()
        ->and($data['token_type'])->toBe('Bearer');
});

it('rejects invalid credentials', function () {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@procurement.local',
        'password' => 'wrong-password',
    ])->assertStatus(401)
        ->assertJsonPath('error', 'InvalidCredentials');
});

it('validates login input', function () {
    $this->postJson('/api/v1/auth/login', ['email' => 'not-an-email'])
        ->assertStatus(422);
});

it('rotates the refresh token on refresh', function () {
    $data = loginPayload();

    $response = $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $data['refresh_token'],
    ])->assertOk()->json('data');

    expect($response['refresh_token'])->not->toBe($data['refresh_token'])
        ->and($response['access_token'])->not->toBeEmpty();

    expect(RefreshToken::query()->where('token_hash', hash('sha256', $data['refresh_token']))->first()->rotated_at)
        ->not->toBeNull();
});

it('detects reuse of a rotated refresh token and revokes the whole family', function () {
    $data = loginPayload();

    $second = $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $data['refresh_token'],
    ])->assertOk()->json('data');

    $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $data['refresh_token'],
    ])->assertStatus(401);

    $activeCount = RefreshToken::query()
        ->whereNull('revoked_at')
        ->count();

    expect($activeCount)->toBe(0);

    $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $second['refresh_token'],
    ])->assertStatus(401);
});

it('rejects an unknown refresh token without leaking information', function () {
    $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => str_repeat('x', 64),
    ])->assertStatus(401);
});

it('logs out and revokes access', function () {
    $data = loginPayload();

    $this->withToken($data['access_token'])
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    expect(PersonalAccessToken::count())->toBe(0)
        ->and(RefreshToken::query()->whereNull('revoked_at')->count())->toBe(0);
});

it('returns the authenticated user from me', function () {
    $data = loginPayload();

    $this->withToken($data['access_token'])
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'admin@procurement.local');
});
