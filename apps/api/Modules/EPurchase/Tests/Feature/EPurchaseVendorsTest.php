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

function seedEpurchaseVendorsSession(User $user): void
{
    app(EPurchaseSessionService::class)->put($user->getAuthIdentifier(), new EPurchaseSession(
        jwt: 'company-session-jwt',
        formToken: 'form-token-abc',
        cookieHeader: 'laravel_session=abc',
        expiresAt: time() + 600,
    ));
}

function epurchaseVendorSearchPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'result' => 'success',
        'msg' => '',
        'data' => [
            [
                'SupplierCode' => 'SUP-00017',
                'short_code' => 'SUP-00017',
                'text' => 'BEST ELECTRONIC SOLUTION',
                'SupplierName' => 'BEST ELECTRONIC SOLUTION',
                'company_name' => 'BEST ELECTRONIC SOLUTION',
                'name_en' => 'BEST ELECTRONIC SOLUTION',
                'name_kh' => 'ហាងលក់ឧបករណ៍',
                'id' => 17,
            ],
            [
                'SupplierCode' => 'SUP-00660',
                'short_code' => 'SUP-00660',
                'text' => 'Best Pest Control',
                'SupplierName' => 'Best Pest Control',
                'company_name' => 'Best Pest Control',
                'name_en' => 'Best Pest Control',
                'name_kh' => 'ការពារសត្វល្អិត',
                'id' => 660,
            ],
        ],
    ], $overrides);
}

function epurchaseVendorInfoPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'result' => 'success',
        'msg' => '',
        'data' => [
            'id' => 17,
            'code' => 'SUP-00017',
            'name_kh' => 'ហាងលក់ឧបករណ៍',
            'name_en' => 'BEST ELECTRONIC SOLUTION',
            'phone' => '012 797 963 / 017 819 419',
            'address' => '594 E0 Phnom Penh',
            'email' => 'best.electronic9@gmail.com',
            'payment_term' => 'Credit 1 month',
            'vat_percentage' => 10,
            'is_onboard' => 1,
            'status' => 1,
        ],
    ], $overrides);
}

function searchEpurchaseVendors(array $query = []): TestResponse
{
    return test()->actingAs(test()->user, 'sanctum')
        ->getJson('/api/v1/epurchase/vendor-search'.(empty($query) ? '' : '?'.http_build_query($query)));
}

function showEpurchaseVendorInfo(array $payload): TestResponse
{
    return test()->actingAs(test()->user, 'sanctum')
        ->postJson('/api/v1/epurchase/vendor-info', $payload);
}

it('requires authentication for vendor search', function () {
    $this->getJson('/api/v1/epurchase/vendor-search')
        ->assertStatus(401);
});

it('returns 401 EPurchaseSessionExpired when no company session for vendor search', function () {
    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/epurchase/vendor-search')
        ->assertStatus(401)
        ->assertJsonPath('error', 'EPurchaseSessionExpired')
        ->assertJsonPath('statusCode', 401);
});

it('posts term to getVentors and returns search rows', function () {
    seedEpurchaseVendorsSession($this->user);
    Http::fake(['*/getVentors*' => Http::response(epurchaseVendorSearchPayload())]);

    $response = searchEpurchaseVendors(['term' => 'best'])
        ->assertOk()
        ->assertJsonPath('data.0.id', 17)
        ->assertJsonPath('data.0.code', 'SUP-00017')
        ->assertJsonPath('data.0.nameEn', 'BEST ELECTRONIC SOLUTION')
        ->assertJsonPath('data.0.nameKhmer', 'ហាងលក់ឧបករណ៍')
        ->assertJsonPath('data.0.text', 'BEST ELECTRONIC SOLUTION')
        ->assertJsonPath('data.1.id', 660);

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), '/getVentors')
            && $request->method() === 'POST'
            && $request->data()['term'] === 'best';
    });

    expect($response->json('data'))->toHaveCount(2);
});

it('forgets the cached session and returns 401 when upstream rejects vendor search', function () {
    seedEpurchaseVendorsSession($this->user);
    Http::fake(['*/getVentors*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

    searchEpurchaseVendors()
        ->assertStatus(401)
        ->assertJsonPath('error', 'EPurchaseSessionExpired');

    expect(app(EPurchaseSessionService::class)->get($this->user->getAuthIdentifier()))->toBeNull();
});

it('returns 502 when the company system is unreachable for vendor search', function () {
    seedEpurchaseVendorsSession($this->user);
    Http::fake(['*/getVentors*' => Http::failedConnection()]);

    searchEpurchaseVendors()
        ->assertStatus(502)
        ->assertJsonPath('error', 'EPurchaseUnavailable');
});

it('requires authentication for vendor info', function () {
    $this->postJson('/api/v1/epurchase/vendor-info', ['supplier_code' => '17'])
        ->assertStatus(401);
});

it('returns 422 when supplier_code is missing', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/epurchase/vendor-info', [])
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);
});

it('returns vendor info with phone and address', function () {
    seedEpurchaseVendorsSession($this->user);
    Http::fake(['*/getVendorInfo*' => Http::response(epurchaseVendorInfoPayload())]);

    showEpurchaseVendorInfo(['supplier_code' => '17'])
        ->assertOk()
        ->assertJsonPath('data.id', 17)
        ->assertJsonPath('data.code', 'SUP-00017')
        ->assertJsonPath('data.nameEn', 'BEST ELECTRONIC SOLUTION')
        ->assertJsonPath('data.nameKhmer', 'ហាងលក់ឧបករណ៍')
        ->assertJsonPath('data.phone', '012 797 963 / 017 819 419')
        ->assertJsonPath('data.address', '594 E0 Phnom Penh')
        ->assertJsonPath('data.email', 'best.electronic9@gmail.com')
        ->assertJsonPath('data.paymentTerm', 'Credit 1 month')
        ->assertJsonPath('data.vatPercentage', '10');

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), '/getVendorInfo')
            && $request->method() === 'POST'
            && $request->data()['SupplierCode'] === '17';
    });
});

it('forgets the cached session and returns 401 when upstream rejects vendor info', function () {
    seedEpurchaseVendorsSession($this->user);
    Http::fake(['*/getVendorInfo*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

    showEpurchaseVendorInfo(['supplier_code' => '17'])
        ->assertStatus(401)
        ->assertJsonPath('error', 'EPurchaseSessionExpired');

    expect(app(EPurchaseSessionService::class)->get($this->user->getAuthIdentifier()))->toBeNull();
});

it('returns 502 when the company system is unreachable for vendor info', function () {
    seedEpurchaseVendorsSession($this->user);
    Http::fake(['*/getVendorInfo*' => Http::failedConnection()]);

    showEpurchaseVendorInfo(['supplier_code' => '17'])
        ->assertStatus(502)
        ->assertJsonPath('error', 'EPurchaseUnavailable');
});
