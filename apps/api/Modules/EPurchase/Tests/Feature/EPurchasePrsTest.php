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

function seedEpurchasePrsSession(User $user): void
{
    app(EPurchaseSessionService::class)->put($user->getAuthIdentifier(), new EPurchaseSession(
        jwt: 'company-session-jwt',
        formToken: 'form-token-abc',
        cookieHeader: 'laravel_session=abc',
        expiresAt: time() + 600,
    ));
}

function epurchasePrsPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'draw' => '1',
        'recordsTotal' => 22819,
        'recordsFiltered' => 22819,
        'data' => [
            [
                'no' => 1,
                'id' => 24768,
                'Purpose' => 'Office stationery restock',
                'amount' => 11,
                'RefNum' => 'PR-CCV-20260915-001',
                'requester' => 'Channarak Leng',
                'requestDept' => 130,
                'created_at' => '2026-09-15 17:52:26',
                'status' => 'Checked',
                'isChecked' => 1,
                'isApproved' => null,
                'purchase_status' => null,
            ],
            [
                'no' => 2,
                'id' => 24767,
                'Purpose' => 'A4 paper for the office',
                'amount' => 49.85,
                'RefNum' => 'PR-CCV-20260825-001',
                'requester' => 'Channarak Leng',
                'requestDept' => 130,
                'created_at' => '2026-08-25 16:42:23',
                'status' => 'Approved',
                'isChecked' => 1,
                'isApproved' => 1,
                'purchase_status' => 'Ordered',
            ],
        ],
    ], $overrides);
}

function getEpurchasePrs(array $query = []): TestResponse
{
    return test()->actingAs(test()->user, 'sanctum')
        ->getJson('/api/v1/epurchase/prs'.(empty($query) ? '' : '?'.http_build_query($query)));
}

function epurchasePrDetailPayload(array $overrides = []): array
{
    return array_replace_recursive([
        [
            'DT_RowIndex' => 1,
            'currency' => 'USD',
            'id' => 56789,
            'item_code' => 'STA-A4-001',
            'description' => 'A4 Copy Paper 80gsm',
            'description3' => 'Khmer description text',
            'campus_code' => 'CCV',
            'division_code' => 'ADM',
            'department_code' => 'PUR',
            'qty' => 5,
            'canceled' => 0,
            'received' => 0,
            'purchase_order_qty' => 0,
            'remain_after_po' => 5,
            'unit_type' => 'Box',
            'unit_price' => 4.99,
            'sub_total' => 24.95,
            'status' => 'Open',
            'force_close' => 0,
        ],
        [
            'DT_RowIndex' => 2,
            'currency' => 'USD',
            'id' => 56790,
            'item_code' => 'STA-PEN-002',
            'description' => 'Ballpoint Pen Blue',
            'description3' => '',
            'campus_code' => 'CCV',
            'division_code' => 'ADM',
            'department_code' => 'PUR',
            'qty' => 20,
            'canceled' => 0,
            'received' => 0,
            'purchase_order_qty' => 0,
            'remain_after_po' => 20,
            'unit_type' => 'Pcs',
            'unit_price' => 0.5,
            'sub_total' => 10,
            'status' => 'Open',
            'force_close' => 0,
        ],
    ], $overrides);
}

function getEpurchasePrDetail(string $prId = '24760'): TestResponse
{
    return test()->actingAs(test()->user, 'sanctum')
        ->getJson('/api/v1/epurchase/prs/'.$prId);
}

it('requires authentication', function () {
    $this->getJson('/api/v1/epurchase/prs')
        ->assertStatus(401);
});

it('returns 401 EPurchaseSessionExpired when no company session is cached', function () {
    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/epurchase/prs')
        ->assertStatus(401)
        ->assertJsonPath('error', 'EPurchaseSessionExpired')
        ->assertJsonPath('statusCode', 401);
});

it('returns a page of company purchase requisitions with meta', function () {
    seedEpurchasePrsSession($this->user);
    Http::fake(['*getRequestList*' => Http::response(epurchasePrsPayload())]);

    $response = getEpurchasePrs(['page' => 1, 'per_page' => 10])
        ->assertOk()
        ->assertJsonPath('meta.page', 1)
        ->assertJsonPath('meta.perPage', 10)
        ->assertJsonPath('meta.total', 22819)
        ->assertJsonPath('data.0.id', 24768)
        ->assertJsonPath('data.0.no', 1)
        ->assertJsonPath('data.0.refNum', 'PR-CCV-20260915-001')
        ->assertJsonPath('data.0.purpose', 'Office stationery restock')
        ->assertJsonPath('data.0.amount', 11)
        ->assertJsonPath('data.0.requester', 'Channarak Leng')
        ->assertJsonPath('data.0.createdAt', '2026-09-15 17:52:26')
        ->assertJsonPath('data.0.status', 'Checked')
        ->assertJsonPath('data.0.purchaseStatus', null)
        ->assertJsonPath('data.1.purchaseStatus', 'Ordered');

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), '/api/pr/getRequestList')
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer company-session-jwt')
            && $request['start'] === '0'
            && $request['length'] === '10'
            && $request['_token'] === 'form-token-abc'
            && $request['order[0][column]'] === '6'
            && $request['columns[0][data]'] === 'id'
            && $request['columns[9][name]'] === 'Action';
    });

    expect($response->json('data'))->toHaveCount(2);
});

it('passes page and search to the company system', function () {
    seedEpurchasePrsSession($this->user);
    Http::fake(['*getRequestList*' => Http::response(epurchasePrsPayload([
        'recordsFiltered' => 1,
        'data' => [epurchasePrsPayload()['data'][0]],
    ]))]);

    getEpurchasePrs(['page' => 2, 'per_page' => 2, 'search' => 'PR-CCV'])
        ->assertOk()
        ->assertJsonPath('meta.page', 2)
        ->assertJsonPath('meta.perPage', 2)
        ->assertJsonPath('meta.total', 1);

    Http::assertSent(function ($request): bool {
        return $request['start'] === '2'
            && $request['length'] === '2'
            && $request['search[value]'] === 'PR-CCV';
    });
});

it('refreshes the company session and retries when the upstream rejects it once', function () {
    seedEpurchasePrsSession($this->user);
    Http::fake([
        '*getRequestList*' => Http::sequence()
            ->push(['message' => 'Unauthenticated.'], 401)
            ->push(epurchasePrsPayload()),
        '*/default_user_access/refresh*' => Http::response([
            'access_token' => 'renewed-company-jwt',
            'token_type' => 'bearer',
            'expires_in' => 7200,
        ]),
    ]);

    getEpurchasePrs()
        ->assertOk()
        ->assertJsonPath('data.0.refNum', 'PR-CCV-20260915-001');

    expect(app(EPurchaseSessionService::class)->get($this->user->getAuthIdentifier())?->jwt)
        ->toBe('renewed-company-jwt');
});

it('forgets the cached session and returns 401 when the refresh is also rejected', function () {
    seedEpurchasePrsSession($this->user);
    Http::fake([
        '*getRequestList*' => Http::response(['message' => 'Unauthenticated.'], 401),
        '*/default_user_access/refresh*' => Http::response(['message' => 'Unauthenticated.'], 401),
    ]);

    getEpurchasePrs()
        ->assertStatus(401)
        ->assertJsonPath('error', 'EPurchaseSessionExpired');

    expect(app(EPurchaseSessionService::class)->get($this->user->getAuthIdentifier()))->toBeNull();
});

it('returns 502 when the company system is unreachable', function () {
    seedEpurchasePrsSession($this->user);
    Http::fake(['*getRequestList*' => Http::failedConnection()]);

    getEpurchasePrs()
        ->assertStatus(502)
        ->assertJsonPath('error', 'EPurchaseUnavailable');
});

it('requires authentication for the pr detail', function () {
    $this->getJson('/api/v1/epurchase/prs/24760')
        ->assertStatus(401);
});

it('returns 401 EPurchaseSessionExpired when no company session is cached for the detail', function () {
    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/epurchase/prs/24760')
        ->assertStatus(401)
        ->assertJsonPath('error', 'EPurchaseSessionExpired')
        ->assertJsonPath('statusCode', 401);
});

it('returns the line items of a company purchase requisition', function () {
    seedEpurchasePrsSession($this->user);
    Http::fake(['*viewDetail*' => Http::response(epurchasePrDetailPayload())]);

    $response = getEpurchasePrDetail('24760')
        ->assertOk()
        ->assertJsonPath('data.0.id', 56789)
        ->assertJsonPath('data.0.itemCode', 'STA-A4-001')
        ->assertJsonPath('data.0.description', 'A4 Copy Paper 80gsm')
        ->assertJsonPath('data.0.description3', 'Khmer description text')
        ->assertJsonPath('data.0.campusCode', 'CCV')
        ->assertJsonPath('data.0.divisionCode', 'ADM')
        ->assertJsonPath('data.0.departmentCode', 'PUR')
        ->assertJsonPath('data.0.qty', 5)
        ->assertJsonPath('data.0.unitType', 'Box')
        ->assertJsonPath('data.0.unitPrice', 4.99)
        ->assertJsonPath('data.0.subTotal', 24.95)
        ->assertJsonPath('data.0.currency', 'USD')
        ->assertJsonPath('data.0.status', 'Open')
        ->assertJsonPath('data.0.canceled', 0)
        ->assertJsonPath('data.0.received', 0)
        ->assertJsonPath('data.0.purchaseOrderQty', 0)
        ->assertJsonPath('data.0.remainAfterPo', 5)
        ->assertJsonPath('data.0.forceClose', 0)
        ->assertJsonPath('data.1.itemCode', 'STA-PEN-002');

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), '/pr-viewDetail/viewDetail')
            && $request->method() === 'GET'
            && $request->hasHeader('Authorization', 'Bearer company-session-jwt')
            && $request['pr_id'] === '24760'
            && $request['getPRDetailTable'] === '1';
    });

    expect($response->json('data'))->toHaveCount(2);
});

it('returns 404 for a non-numeric pr id', function () {
    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/epurchase/prs/not-a-number')
        ->assertNotFound()
        ->assertJsonPath('statusCode', 404);
});

it('refreshes the company session and retries the detail when the upstream rejects it once', function () {
    seedEpurchasePrsSession($this->user);
    Http::fake([
        '*viewDetail*' => Http::sequence()
            ->push(['message' => 'Unauthenticated.'], 401)
            ->push(epurchasePrDetailPayload()),
        '*/default_user_access/refresh*' => Http::response([
            'access_token' => 'renewed-company-jwt',
            'token_type' => 'bearer',
            'expires_in' => 7200,
        ]),
    ]);

    getEpurchasePrDetail('24760')
        ->assertOk()
        ->assertJsonPath('data.0.itemCode', 'STA-A4-001');

    expect(app(EPurchaseSessionService::class)->get($this->user->getAuthIdentifier())?->jwt)
        ->toBe('renewed-company-jwt');
});

it('returns 502 when the company system is unreachable for the detail', function () {
    seedEpurchasePrsSession($this->user);
    Http::fake(['*viewDetail*' => Http::failedConnection()]);

    getEpurchasePrDetail('24760')
        ->assertStatus(502)
        ->assertJsonPath('error', 'EPurchaseUnavailable');
});

it('returns 502 when the upstream detail payload is not a list of lines', function () {
    seedEpurchasePrsSession($this->user);
    Http::fake(['*viewDetail*' => Http::response(['result' => 'error'])]);

    getEpurchasePrDetail('24760')
        ->assertStatus(502)
        ->assertJsonPath('error', 'EPurchaseUnavailable');
});
