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
            'real_position' => 'Procurement Officer',
        ],
    ], $overrides);
}

function companyLoginPhotoDataUri(): string
{
    // 1×1 transparent PNG.
    return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
}

function companySignatureDataUri(): string
{
    // 1×1 gray+alpha PNG — different bytes from the photo, still a valid image.
    return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC';
}

function companyMyInfoPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'result' => 'success',
        'msg' => '',
        'data' => [
            'user' => [
                'id' => 1963,
                'card_id' => '3665',
                'name' => 'Vun Thy',
                'real_position' => 'Senior Procurement Officer',
            ],
            'signature' => companySignatureDataUri(),
        ],
    ], $overrides);
}

/**
 * Login makes two upstream calls now: the credential exchange and the
 * profile fetch. Route each URL to its own fake so sequences stay aligned.
 */
function fakeCompanyLoginWith(mixed $login, mixed $myInfo): void
{
    Http::fake([
        '*default_user_access/login*' => $login,
        '*dashboard/getMyInfo*' => $myInfo,
    ]);
}

function postCompanyLogin(array $payload = []): TestResponse
{
    return test()->postJson('/api/v1/auth/company-login', array_merge([
        'employee_id' => '3665',
        'password' => 'secret-company-password',
    ], $payload));
}

it('creates a new local user on first company login and returns a token pair', function () {
    Storage::fake('public');

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

    Storage::fake('public');

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

    Storage::fake('public');

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
    Storage::fake('public');

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

it('replaces the previous sso avatar file when the company photo changes', function () {
    Storage::fake('public');

    fakeCompanyLoginWith(
        Http::sequence()
            ->push(companyLoginSuccessPayload())
            ->push(companyLoginSuccessPayload(['userPhoto' => companySignatureDataUri()])),
        Http::response(companyMyInfoPayload()),
    );

    postCompanyLogin()->assertOk();

    $user = User::query()->where('email', 'vun.thy@mjqeducation.edu.kh')->firstOrFail();
    $firstPath = $user->avatar_path;

    postCompanyLogin()->assertOk();

    $secondPath = $user->refresh()->avatar_path;

    expect($secondPath)->not->toBe($firstPath)
        ->and(Storage::disk('public')->exists($secondPath))->toBeTrue()
        ->and(Storage::disk('public')->exists($firstPath))->toBeFalse()
        ->and(Storage::disk('public')->allFiles('avatars'))->toBe([$secondPath]);
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

it('stores the company real_position on the user and exposes it through the auth endpoints', function () {
    Storage::fake('public');

    Http::fake(['*' => Http::response(companyLoginSuccessPayload())]);

    $data = postCompanyLogin()->assertOk()->json('data');

    expect($data['user']['position'])->toBe('Procurement Officer');

    $user = User::query()->where('email', 'vun.thy@mjqeducation.edu.kh')->firstOrFail();

    expect($user->position)->toBe('Procurement Officer');

    $this->withToken($data['access_token'])
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.position', 'Procurement Officer');
});

it('refreshes the stored position on later logins and keeps it when the company omits real_position', function () {
    Storage::fake('public');

    fakeCompanyLoginWith(
        Http::sequence()
            ->push(companyLoginSuccessPayload(['user' => ['real_position' => 'Procurement Officer']]))
            ->push(companyLoginSuccessPayload(['user' => ['real_position' => 'Head of Procurement']]))
            ->push(companyLoginSuccessPayload(['user' => ['real_position' => null]])),
        Http::response(companyMyInfoPayload()),
    );

    $first = postCompanyLogin()->assertOk()->json('data.user.position');
    $second = postCompanyLogin()->assertOk()->json('data.user.position');
    $third = postCompanyLogin()->assertOk()->json('data.user.position');

    $user = User::query()->where('email', 'vun.thy@mjqeducation.edu.kh')->firstOrFail();

    expect($first)->toBe('Procurement Officer')
        ->and($second)->toBe('Head of Procurement')
        ->and($third)->toBe('Head of Procurement')
        ->and($user->position)->toBe('Head of Procurement');
});

it('stores the company signature as an sso file on login and exposes it through the auth endpoints', function () {
    Storage::fake('public');

    fakeCompanyLoginWith(
        Http::response(companyLoginSuccessPayload()),
        Http::response(companyMyInfoPayload()),
    );

    $data = postCompanyLogin()->assertOk()->json('data');

    $user = User::query()->where('email', 'vun.thy@mjqeducation.edu.kh')->firstOrFail();
    $path = $user->signature_path;

    expect($path)->toStartWith('signatures/sso_'.$user->getKey().'_')
        ->and(Storage::disk('public')->exists($path))->toBeTrue()
        ->and($data['user']['signature'])->toBe(Storage::disk('public')->url($path));

    $this->withToken($data['access_token'])
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.signature', Storage::disk('public')->url($path));
});

it('never overwrites a manually uploaded signature with the company signature', function () {
    Storage::fake('public');

    $existing = User::factory()->create(['email' => 'vun.thy@mjqeducation.edu.kh']);

    $manualPath = 'signatures/user_'.$existing->getKey().'_manual.png';
    Storage::disk('public')->put($manualPath, 'manual-upload-bytes');
    $existing->update(['signature_path' => $manualPath]);

    fakeCompanyLoginWith(
        Http::response(companyLoginSuccessPayload()),
        Http::response(companyMyInfoPayload()),
    );

    postCompanyLogin()->assertOk();

    expect($existing->refresh()->signature_path)->toBe($manualPath)
        ->and(Storage::disk('public')->exists($manualPath))->toBeTrue()
        ->and(Storage::disk('public')->allFiles('signatures'))->toBe([$manualPath]);
});

it('refreshes the signature on later logins, keeps superseded files, and keeps it when getMyInfo omits it', function () {
    Storage::fake('public');

    $firstSignature = companyLoginPhotoDataUri();
    $secondSignature = companySignatureDataUri();

    fakeCompanyLoginWith(
        Http::response(companyLoginSuccessPayload()),
        Http::sequence()
            ->push(companyMyInfoPayload(['data' => ['signature' => $firstSignature]]))
            ->push(companyMyInfoPayload(['data' => ['signature' => $secondSignature]]))
            ->push(companyMyInfoPayload(['data' => ['signature' => null]])),
    );

    postCompanyLogin()->assertOk();
    $user = User::query()->where('email', 'vun.thy@mjqeducation.edu.kh')->firstOrFail();
    $firstPath = $user->signature_path;

    postCompanyLogin()->assertOk();
    $user->refresh();
    $secondPath = $user->signature_path;

    postCompanyLogin()->assertOk();

    expect($firstPath)->toStartWith('signatures/sso_')
        ->and($secondPath)->not->toBe($firstPath)
        // Superseded files stay on disk: approval snapshots reference them.
        ->and(Storage::disk('public')->exists($firstPath))->toBeTrue()
        ->and(Storage::disk('public')->exists($secondPath))->toBeTrue()
        ->and($user->refresh()->signature_path)->toBe($secondPath);
});

it('reuses the same signature file when the signature has not changed', function () {
    Storage::fake('public');

    fakeCompanyLoginWith(
        Http::response(companyLoginSuccessPayload()),
        Http::response(companyMyInfoPayload()),
    );

    postCompanyLogin()->assertOk();
    $path = User::query()->where('email', 'vun.thy@mjqeducation.edu.kh')->firstOrFail()->signature_path;

    postCompanyLogin()->assertOk();

    expect(User::query()->where('email', 'vun.thy@mjqeducation.edu.kh')->firstOrFail()->signature_path)
        ->toBe($path)
        ->and(Storage::disk('public')->allFiles('signatures'))->toBe([$path]);
});

it('logs in without a signature when getMyInfo fails', function () {
    Storage::fake('public');

    fakeCompanyLoginWith(
        Http::response(companyLoginSuccessPayload()),
        Http::response(['message' => 'Server Error'], 500),
    );

    $data = postCompanyLogin()->assertOk()->json('data');

    expect(User::query()->where('email', 'vun.thy@mjqeducation.edu.kh')->firstOrFail()->signature_path)
        ->toBeNull()
        ->and($data['user']['signature'])->toBeNull();
});

it('keeps the stored signature when a later getMyInfo fails', function () {
    Storage::fake('public');

    // Stubs accumulate within a test, so the failure rides a sequence
    // instead of re-registering the same URL pattern.
    fakeCompanyLoginWith(
        Http::response(companyLoginSuccessPayload()),
        Http::sequence()
            ->push(companyMyInfoPayload())
            ->push(['message' => 'Server Error'], 500),
    );

    postCompanyLogin()->assertOk();
    $path = User::query()->where('email', 'vun.thy@mjqeducation.edu.kh')->firstOrFail()->signature_path;

    postCompanyLogin()->assertOk();

    expect(User::query()->where('email', 'vun.thy@mjqeducation.edu.kh')->firstOrFail()->signature_path)
        ->toBe($path)
        ->and(Storage::disk('public')->exists($path))->toBeTrue();
});
