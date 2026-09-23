<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Modules\Organization\Models\Entity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Http::preventStrayRequests();

    foreach (['admin', 'staff'] as $role) {
        Role::findOrCreate($role, 'sanctum');
    }

    $this->entity = Entity::query()->create([
        'code' => 'MAIN',
        'name' => 'Main Organization',
        'timezone' => 'UTC',
        'locale' => 'en',
    ]);

    config(['epurchase.default_entity' => 'MAIN']);
});

function companyLoginSuccessPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'result' => 'success',
        'msg' => 'Login successfully',
        'data' => 'company-session-jwt',
        'userPhoto' => companyLoginPhotoDataUri(),
        'user' => [
            'id' => 1963,
            'card_id' => '3665',
            'name' => 'Vun Thy',
            'username' => '3665',
            'email' => 'vun.thy@mjqeducation.edu.kh',
        ],
    ], $overrides);
}

function companyLoginPhotoDataUri(): string
{
    // 1×1 transparent PNG.
    return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
}

function postCompanyLogin(array $payload = []): TestResponse
{
    return test()->postJson('/api/v1/auth/company-login', array_merge([
        'employee_id' => '3665',
        'password' => 'secret-company-password',
    ], $payload));
}

it('creates a new local user on first company login and returns a token pair', function () {
    Http::fake(['*' => Http::response(companyLoginSuccessPayload())]);

    $data = postCompanyLogin()
        ->assertOk()
        ->json('data');

    expect($data['user']['email'])->toBe('vun.thy@mjqeducation.edu.kh')
        ->and($data['access_token'])->not->toBeEmpty()
        ->and($data['refresh_token'])->not->toBeEmpty()
        ->and($data['token_type'])->toBe('Bearer');

    $user = User::query()->where('email', 'vun.thy@mjqeducation.edu.kh')->first();

    expect($user)->not->toBeNull()
        ->and($user->entity_id)->toBe($this->entity->getKey())
        ->and($user->hasRole('staff'))->toBeTrue();

    $this->withToken($data['access_token'])
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'vun.thy@mjqeducation.edu.kh');
});

it('matches an existing local user by email without creating a duplicate', function () {
    $existing = User::factory()->create([
        'email' => 'vun.thy@mjqeducation.edu.kh',
        'name' => 'Old Name',
    ]);

    Http::fake(['*' => Http::response(companyLoginSuccessPayload())]);

    postCompanyLogin()->assertOk();

    expect(User::query()->where('email', 'vun.thy@mjqeducation.edu.kh')->count())->toBe(1)
        ->and($existing->fresh()->name)->toBe('Vun Thy');
});

it('returns 401 when the company system rejects the credentials', function () {
    Http::fake(['*' => Http::response([
        'result' => 'failed',
        'msg' => 'Login failed',
    ])]);

    postCompanyLogin()
        ->assertStatus(401)
        ->assertJsonPath('error', 'InvalidCredentials');

    expect(User::query()->where('email', 'vun.thy@mjqeducation.edu.kh')->exists())->toBeFalse();
});

it('returns 401 when the local account has been deleted', function () {
    User::factory()->create([
        'email' => 'vun.thy@mjqeducation.edu.kh',
        'is_active' => false,
    ])->delete();

    Http::fake(['*' => Http::response(companyLoginSuccessPayload())]);

    postCompanyLogin()
        ->assertStatus(401)
        ->assertJsonPath('error', 'InvalidCredentials');

    expect(User::withTrashed()->where('email', 'vun.thy@mjqeducation.edu.kh')->count())->toBe(1);
});

it('returns 502 when the company system is unreachable', function () {
    Http::fake(['*' => Http::failedConnection()]);

    postCompanyLogin()
        ->assertStatus(502)
        ->assertJsonPath('error', 'EPurchaseUnavailable');
});

it('validates company login input with the standard error envelope', function () {
    postCompanyLogin(['employee_id' => '', 'password' => ''])
        ->assertStatus(422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);
});

it('rate limits company login attempts', function () {
    Http::fake(['*' => Http::response([
        'result' => 'failed',
        'msg' => 'Login failed',
    ])]);

    foreach (range(1, 5) as $attempt) {
        postCompanyLogin()->assertStatus(401);
    }

    postCompanyLogin()->assertStatus(429);
});

it('never exposes the company password or company session token in the response', function () {
    Http::fake(['*' => Http::response(companyLoginSuccessPayload())]);

    $content = postCompanyLogin()
        ->assertOk()
        ->getContent();

    expect($content)
        ->not->toContain('secret-company-password')
        ->not->toContain('company-session-jwt');
});

it('applies the company userPhoto as an sso avatar on first login', function () {
    Storage::fake('public');

    Http::fake(['*' => Http::response(companyLoginSuccessPayload())]);

    $data = postCompanyLogin()->assertOk()->json('data');

    $user = User::query()->where('email', 'vun.thy@mjqeducation.edu.kh')->firstOrFail();
    $path = $user->avatar_path;

    expect($path)->toStartWith('avatars/sso_'.$user->getKey().'_')
        ->and(Storage::disk('public')->exists($path))->toBeTrue()
        ->and($data['user']['avatar'])->toBe(Storage::disk('public')->url($path));
});

it('never overwrites a manually uploaded avatar with the company photo', function () {
    Storage::fake('public');

    $existing = User::factory()->create(['email' => 'vun.thy@mjqeducation.edu.kh']);

    $manualPath = 'avatars/user_'.$existing->getKey().'_manual.jpg';
    Storage::disk('public')->put($manualPath, 'manual-upload-bytes');
    $existing->update(['avatar_path' => $manualPath]);

    Http::fake(['*' => Http::response(companyLoginSuccessPayload())]);

    postCompanyLogin()->assertOk();

    expect($existing->refresh()->avatar_path)->toBe($manualPath)
        ->and(Storage::disk('public')->exists($manualPath))->toBeTrue()
        ->and(Storage::disk('public')->allFiles('avatars'))->toBe([$manualPath]);
});

it('skips the company photo silently when userPhoto is absent', function () {
    Storage::fake('public');

    Http::fake(['*' => Http::response(companyLoginSuccessPayload(['userPhoto' => null]))]);

    postCompanyLogin()->assertOk();

    expect(User::query()->where('email', 'vun.thy@mjqeducation.edu.kh')->first()->avatar_path)->toBeNull();
});
