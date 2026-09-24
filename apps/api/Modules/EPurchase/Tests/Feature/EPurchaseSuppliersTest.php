<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Modules\EPurchase\Services\EPurchaseSession;
use Modules\EPurchase\Services\EPurchaseSessionService;
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

    $this->user = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->user->assignRole('staff');

    config(['epurchase.default_entity' => 'MAIN']);
});

function seedEpurchaseSuppliersSession(User $user): void
{
    app(EPurchaseSessionService::class)->put($user->getAuthIdentifier(), new EPurchaseSession(
        jwt: 'company-session-jwt',
        formToken: 'form-token-abc',
        cookieHeader: 'laravel_session=abc',
        expiresAt: time() + 600,
    ));
}

function epurchaseSuppliersPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'recordsTotal' => 3,
        'recordsFiltered' => 3,
        'data' => [
            [
                'id' => 55,
                'code' => 'SUP-00055',
                'company_name' => 'គុយ ឡេង',
                'name_en' => 'Kuy Leng',
                'name_kh' => 'គុយ ឡេង',
                'phone' => '012 876 676',
                'address' => '120 Street 271, Phnom Penh',
                'email' => 'supplier@example.com',
                'supplier_type' => 'Shop',
                'payment_term' => 'Non-Credit',
                'is_onboard' => 1,
                'status' => 1,
            ],
            [
                'id' => 56,
                'code' => 'SUP-00056',
                'company_name' => 'Acme Trading',
                'name_en' => 'Acme Trading Co',
                'name_kh' => 'អាកមេ',
                'phone' => '011 222 333',
                'address' => '45 Monitor St, Battambang',
                'email' => 'NA',
                'supplier_type' => 'Company',
                'payment_term' => '30 Days',
                'is_onboard' => 0,
                'status' => 0,
            ],
            [
                'id' => 57,
                'code' => 'SUP-00057',
                'company_name' => 'Paper Co',
                'name_en' => 'Paper Co',
                'name_kh' => '',
                'phone' => '',
                'address' => '',
                'email' => '',
                'supplier_type' => 'Shop',
                'payment_term' => 'Cash',
                'is_onboard' => 1,
                'status' => 1,
            ],
        ],
    ], $overrides);
}

function getEpurchaseSuppliers(array $query = []): TestResponse
{
    return test()->actingAs(test()->user, 'sanctum')
        ->getJson('/api/v1/epurchase/suppliers'.(empty($query) ? '' : '?'.http_build_query($query)));
}

it('requires authentication', function () {
    $this->getJson('/api/v1/epurchase/suppliers')
        ->assertStatus(401);
});

it('returns 401 EPurchaseSessionExpired when no company session is cached', function () {
    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/epurchase/suppliers')
        ->assertStatus(401)
        ->assertJsonPath('error', 'EPurchaseSessionExpired')
        ->assertJsonPath('statusCode', 401);
});

it('returns a page of company suppliers with meta', function () {
    seedEpurchaseSuppliersSession($this->user);
    Http::fake(['*/suppliers-master-list*' => Http::response(epurchaseSuppliersPayload())]);

    $response = getEpurchaseSuppliers(['page' => 1, 'per_page' => 10])
        ->assertOk()
        ->assertJsonPath('meta.page', 1)
        ->assertJsonPath('meta.perPage', 10)
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('data.0.id', 55)
        ->assertJsonPath('data.0.code', 'SUP-00055')
        ->assertJsonPath('data.0.name', 'Kuy Leng')
        ->assertJsonPath('data.0.phone', '012 876 676')
        ->assertJsonPath('data.0.email', 'supplier@example.com')
        ->assertJsonPath('data.0.address', '120 Street 271, Phnom Penh')
        ->assertJsonPath('data.0.supplierType', 'Shop')
        ->assertJsonPath('data.0.paymentTerm', 'Non-Credit')
        ->assertJsonPath('data.0.isOnboard', '1')
        ->assertJsonPath('data.0.status', '1');

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), '/suppliers-master-list')
            && $request['start'] === '0'
            && $request['length'] === '10';
    });

    expect($response->json('data'))->toHaveCount(3);
    expect($response->json('data.1.name'))->toBe('Acme Trading Co');
    expect($response->json('data.2.name'))->toBe('Paper Co');
});

it('falls back to the Khmer name when English is missing', function () {
    seedEpurchaseSuppliersSession($this->user);

    $payload = epurchaseSuppliersPayload();
    $payload['data'][0]['name_en'] = '';
    $payload['data'][0]['name_kh'] = 'គុយ ឡេង';
    Http::fake(['*/suppliers-master-list*' => Http::response($payload)]);

    getEpurchaseSuppliers()
        ->assertOk()
        ->assertJsonPath('data.0.name', 'គុយ ឡេង');
});

it('passes page and search to the company system', function () {
    seedEpurchaseSuppliersSession($this->user);
    Http::fake(['*/suppliers-master-list*' => Http::response(epurchaseSuppliersPayload([
        'recordsFiltered' => 1,
        'data' => [epurchaseSuppliersPayload()['data'][0]],
    ]))]);

    getEpurchaseSuppliers(['page' => 2, 'per_page' => 2, 'search' => 'shop'])
        ->assertOk()
        ->assertJsonPath('meta.page', 2)
        ->assertJsonPath('meta.perPage', 2)
        ->assertJsonPath('meta.total', 1);

    Http::assertSent(function ($request): bool {
        return $request['start'] === '2'
            && $request['length'] === '2'
            && $request['search[value]'] === 'shop';
    });
});

it('filters to onboarded suppliers when is_onboard=1', function () {
    seedEpurchaseSuppliersSession($this->user);
    Http::fake(['*/suppliers-master-list*' => Http::response(epurchaseSuppliersPayload())]);

    $response = getEpurchaseSuppliers(['is_onboard' => 1])
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.code', 'SUP-00055')
        ->assertJsonPath('data.1.code', 'SUP-00057');

    expect($response->json('data'))->toHaveCount(2);
});

it('rejects is_onboard values other than 1 with a 422 envelope', function () {
    seedEpurchaseSuppliersSession($this->user);

    getEpurchaseSuppliers(['is_onboard' => '0'])
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);
});

it('forgets the cached session and returns 401 when upstream rejects it', function () {
    seedEpurchaseSuppliersSession($this->user);
    Http::fake(['*/suppliers-master-list*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

    getEpurchaseSuppliers()
        ->assertStatus(401)
        ->assertJsonPath('error', 'EPurchaseSessionExpired');

    expect(app(EPurchaseSessionService::class)->get($this->user->getAuthIdentifier()))->toBeNull();
});

it('returns 502 when the company system is unreachable', function () {
    seedEpurchaseSuppliersSession($this->user);
    Http::fake(['*/suppliers-master-list*' => Http::failedConnection()]);

    getEpurchaseSuppliers()
        ->assertStatus(502)
        ->assertJsonPath('error', 'EPurchaseUnavailable');
});
