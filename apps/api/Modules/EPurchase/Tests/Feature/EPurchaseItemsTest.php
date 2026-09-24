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

function seedEpurchaseSession(User $user): void
{
    app(EPurchaseSessionService::class)->put($user->getAuthIdentifier(), new EPurchaseSession(
        jwt: 'company-session-jwt',
        formToken: 'form-token-abc',
        cookieHeader: 'laravel_session=abc',
        expiresAt: time() + 600,
    ));
}

function epurchaseItemsPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'recordsTotal' => 3,
        'recordsFiltered' => 3,
        'data' => [
            [
                'ItemCode' => 'ITM-1',
                'Description' => 'A4 Paper',
                'LongDescription' => 'A4 copy paper',
                'BaseItemUnit' => 'Box',
                'category' => 'Stationery',
                'sub_category' => 'Paper',
                'estimate_price' => '3.75',
                'avg_price_3_months' => '3.50',
                'Status' => 'Active',
            ],
            [
                'ItemCode' => 'ITM-2',
                'Description' => 'USB Cable',
                'LongDescription' => 'USB-A to USB-C',
                'BaseItemUnit' => 'Pcs',
                'category' => 'Electronics',
                'sub_category' => 'Cables',
                'estimate_price' => '2.00',
                'avg_price_3_months' => '1.90',
                'Status' => 'Inactive',
            ],
            [
                'ItemCode' => 'ITM-3',
                'Description' => 'Stapler',
                'LongDescription' => 'Standard stapler',
                'BaseItemUnit' => 'Pcs',
                'category' => 'Stationery',
                'sub_category' => 'Office',
                'estimate_price' => '5.00',
                'avg_price_3_months' => '4.80',
                'Status' => 'Active',
            ],
        ],
    ], $overrides);
}

function getEpurchaseItems(array $query = []): TestResponse
{
    return test()->actingAs(test()->user, 'sanctum')
        ->getJson('/api/v1/epurchase/items'.(empty($query) ? '' : '?'.http_build_query($query)));
}

it('requires authentication', function () {
    $this->getJson('/api/v1/epurchase/items')
        ->assertStatus(401);
});

it('returns 401 EPurchaseSessionExpired when no company session is cached', function () {
    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/epurchase/items')
        ->assertStatus(401)
        ->assertJsonPath('error', 'EPurchaseSessionExpired')
        ->assertJsonPath('statusCode', 401);
});

it('returns a page of company items with meta', function () {
    seedEpurchaseSession($this->user);
    Http::fake(['*/items-master-list*' => Http::response(epurchaseItemsPayload())]);

    $response = getEpurchaseItems(['page' => 1, 'per_page' => 10])
        ->assertOk()
        ->assertJsonPath('meta.page', 1)
        ->assertJsonPath('meta.perPage', 10)
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('data.0.code', 'ITM-1')
        ->assertJsonPath('data.0.description', 'A4 Paper')
        ->assertJsonPath('data.0.category', 'Stationery')
        ->assertJsonPath('data.0.subCategory', 'Paper')
        ->assertJsonPath('data.0.uom', 'Box')
        ->assertJsonPath('data.0.estimatePrice', 3.75)
        ->assertJsonPath('data.0.avgPrice', 3.5)
        ->assertJsonPath('data.0.status', 'Active');

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), '/items-master-list')
            && $request['start'] === '0'
            && $request['length'] === '10';
    });

    expect($response->json('data'))->toHaveCount(3);
});

it('passes page and search to the company system', function () {
    seedEpurchaseSession($this->user);
    Http::fake(['*/items-master-list*' => Http::response(epurchaseItemsPayload([
        'recordsFiltered' => 1,
        'data' => [epurchaseItemsPayload()['data'][0]],
    ]))]);

    getEpurchaseItems(['page' => 2, 'per_page' => 2, 'search' => 'paper'])
        ->assertOk()
        ->assertJsonPath('meta.page', 2)
        ->assertJsonPath('meta.perPage', 2)
        ->assertJsonPath('meta.total', 1);

    Http::assertSent(function ($request): bool {
        return $request['start'] === '2'
            && $request['length'] === '2'
            && $request['search[value]'] === 'paper';
    });
});

it('forgets the cached session and returns 401 when upstream rejects it', function () {
    seedEpurchaseSession($this->user);
    Http::fake(['*/items-master-list*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

    getEpurchaseItems()
        ->assertStatus(401)
        ->assertJsonPath('error', 'EPurchaseSessionExpired');

    expect(app(EPurchaseSessionService::class)->get($this->user->getAuthIdentifier()))->toBeNull();
});

it('returns 502 when the company system is unreachable', function () {
    seedEpurchaseSession($this->user);
    Http::fake(['*/items-master-list*' => Http::failedConnection()]);

    getEpurchaseItems()
        ->assertStatus(502)
        ->assertJsonPath('error', 'EPurchaseUnavailable');
});
