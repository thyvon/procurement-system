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

function seedEpurchasePosSession(User $user): void
{
    app(EPurchaseSessionService::class)->put($user->getAuthIdentifier(), new EPurchaseSession(
        jwt: 'company-session-jwt',
        formToken: 'form-token-abc',
        cookieHeader: 'laravel_session=abc',
        expiresAt: time() + 600,
    ));
}

function epurchasePosPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'draw' => '1',
        'recordsTotal' => 1270,
        'recordsFiltered' => 1270,
        'data' => [
            [
                'no' => 1,
                'id' => 1283,
                'transaction_type' => 'POG',
                'poRefNum' => 'PO-202608-00124',
                'requestDate' => '2026-08-11 15:05:54',
                'purpose' => 'Site materials restock',
                'prepareByName' => 'Chanrotha Kiv',
                'created_at' => '2026-08-11 15:04:03',
                'amount' => 15.4,
                'isChecked' => null,
                'isApproved' => null,
                'is_complete' => 0,
                'status' => 'Acknowledge',
                'is_get_xml' => 0,
                'currency' => 'USD',
                'own_po' => 1,
                'return_reason' => '',
                'vendorName' => 'MANA CONSTRUCTION MATERIAL SALES',
                'is_canceling' => 0,
                'purchase_status' => 'Pending',
            ],
            [
                'no' => 3,
                'id' => 1281,
                'transaction_type' => 'POG',
                'poRefNum' => 'PO-202603-00122',
                'requestDate' => '2026-03-31 09:13:28',
                'purpose' => 'Printing supplies',
                'prepareByName' => 'Channarak Leng',
                'created_at' => '2026-03-31 09:11:43',
                'amount' => 10000,
                'isChecked' => 1,
                'isApproved' => 1,
                'is_complete' => 0,
                'status' => 'Approved',
                'is_get_xml' => 0,
                'currency' => 'KHR',
                'own_po' => 1,
                'return_reason' => '',
                'vendorName' => 'M PRINTING AND ADVERTISING',
                'is_canceling' => 0,
                'purchase_status' => 'Pending',
            ],
        ],
    ], $overrides);
}

function getEpurchasePos(array $query = []): TestResponse
{
    return test()->actingAs(test()->user, 'sanctum')
        ->getJson('/api/v1/epurchase/pos'.(empty($query) ? '' : '?'.http_build_query($query)));
}

function epurchasePoDetailPayload(array $overrides = []): array
{
    return array_replace_recursive([
        [
            'currency' => 'USD',
            'id' => 14628,
            'pr_refnum' => 'PR-CEN-20260112-004',
            'item_code' => 'PRO-SO0081',
            'description' => 'Black Refill Ink Marker (LETAO, 990 Plus, 50g)',
            'description2' => '',
            'po_description' => null,
            'qty' => 2520,
            'unit' => 'BOTTLE',
            'campus_code' => 'CEN',
            'division_code' => 'Other',
            'department_code' => 'PROD',
            'location' => 'PROD CEN WH',
            'unitCost' => 0.4,
            'delivery_fee' => 0,
            'discount' => 0,
            'vat' => 0,
            'usdAmount' => 1008,
            'purchase_qty' => 0,
            'cancel_qty' => 0,
            'pending_qty' => 2520,
            'status' => 'Pending',
            'force_close' => 0,
        ],
        [
            'currency' => 'KHR',
            'id' => 14629,
            'pr_refnum' => 'PR-CEN-20260112-004',
            'item_code' => 'STA-PEN-014',
            'description' => 'Ballpoint Pen Blue 0.7mm',
            'description2' => 'Box of 50',
            'po_description' => null,
            'qty' => 10,
            'unit' => 'BOX',
            'campus_code' => 'CEN',
            'division_code' => 'ADM',
            'department_code' => 'PROD',
            'location' => 'PROD CEN WH',
            'unitCost' => 25000,
            'delivery_fee' => 0,
            'discount' => 0,
            'vat' => 0,
            'usdAmount' => 6.25,
            'purchase_qty' => 10,
            'cancel_qty' => 0,
            'pending_qty' => 0,
            'status' => 'Approved',
            'force_close' => 0,
        ],
    ], $overrides);
}

function getEpurchasePoDetail(string $poId = '1277'): TestResponse
{
    return test()->actingAs(test()->user, 'sanctum')
        ->getJson('/api/v1/epurchase/pos/'.$poId);
}

it('requires authentication', function () {
    $this->getJson('/api/v1/epurchase/pos')
        ->assertStatus(401);
});

it('returns 401 EPurchaseSessionExpired when no company session is cached', function () {
    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/epurchase/pos')
        ->assertStatus(401)
        ->assertJsonPath('error', 'EPurchaseSessionExpired')
        ->assertJsonPath('statusCode', 401);
});

it('returns a page of company purchase orders with meta', function () {
    seedEpurchasePosSession($this->user);
    Http::fake(['*getPOList*' => Http::response(epurchasePosPayload())]);

    $response = getEpurchasePos(['page' => 1, 'per_page' => 10])
        ->assertOk()
        ->assertJsonPath('meta.page', 1)
        ->assertJsonPath('meta.perPage', 10)
        ->assertJsonPath('meta.total', 1270)
        ->assertJsonPath('data.0.id', 1283)
        ->assertJsonPath('data.0.no', 1)
        ->assertJsonPath('data.0.poRefNum', 'PO-202608-00124')
        ->assertJsonPath('data.0.vendorName', 'MANA CONSTRUCTION MATERIAL SALES')
        ->assertJsonPath('data.0.purpose', 'Site materials restock')
        ->assertJsonPath('data.0.amount', 15.4)
        ->assertJsonPath('data.0.currency', 'USD')
        ->assertJsonPath('data.0.prepareByName', 'Chanrotha Kiv')
        ->assertJsonPath('data.0.createdAt', '2026-08-11 15:04:03')
        ->assertJsonPath('data.0.status', 'Acknowledge')
        ->assertJsonPath('data.0.purchaseStatus', 'Pending')
        ->assertJsonPath('data.1.amount', 10000)
        ->assertJsonPath('data.1.currency', 'KHR');

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), '/api/po/getPOList')
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer company-session-jwt')
            && $request['start'] === '0'
            && $request['length'] === '10'
            && $request['_token'] === 'form-token-abc'
            && $request['order[0][column]'] === '7'
            && $request['columns[0][data]'] === 'id'
            && $request['columns[2][data]'] === 'poRefNum'
            && $request['columns[11][name]'] === 'Action';
    });

    expect($response->json('data'))->toHaveCount(2);
});

it('passes page and search to the company system', function () {
    seedEpurchasePosSession($this->user);
    Http::fake(['*getPOList*' => Http::response(epurchasePosPayload([
        'recordsFiltered' => 1,
        'data' => [epurchasePosPayload()['data'][0]],
    ]))]);

    getEpurchasePos(['page' => 2, 'per_page' => 2, 'search' => 'PO-2026'])
        ->assertOk()
        ->assertJsonPath('meta.page', 2)
        ->assertJsonPath('meta.perPage', 2)
        ->assertJsonPath('meta.total', 1);

    Http::assertSent(function ($request): bool {
        return $request['start'] === '2'
            && $request['length'] === '2'
            && $request['search[value]'] === 'PO-2026';
    });
});

it('refreshes the company session and retries when the upstream rejects it once', function () {
    seedEpurchasePosSession($this->user);
    Http::fake([
        '*getPOList*' => Http::sequence()
            ->push(['message' => 'Unauthenticated.'], 401)
            ->push(epurchasePosPayload()),
        '*/default_user_access/refresh*' => Http::response([
            'access_token' => 'renewed-company-jwt',
            'token_type' => 'bearer',
            'expires_in' => 7200,
        ]),
    ]);

    getEpurchasePos()
        ->assertOk()
        ->assertJsonPath('data.0.poRefNum', 'PO-202608-00124');

    expect(app(EPurchaseSessionService::class)->get($this->user->getAuthIdentifier())?->jwt)
        ->toBe('renewed-company-jwt');
});

it('forgets the cached session and returns 401 when the refresh is also rejected', function () {
    seedEpurchasePosSession($this->user);
    Http::fake([
        '*getPOList*' => Http::response(['message' => 'Unauthenticated.'], 401),
        '*/default_user_access/refresh*' => Http::response(['message' => 'Unauthenticated.'], 401),
    ]);

    getEpurchasePos()
        ->assertStatus(401)
        ->assertJsonPath('error', 'EPurchaseSessionExpired');

    expect(app(EPurchaseSessionService::class)->get($this->user->getAuthIdentifier()))->toBeNull();
});

it('returns 502 when the company system is unreachable', function () {
    seedEpurchasePosSession($this->user);
    Http::fake(['*getPOList*' => Http::failedConnection()]);

    getEpurchasePos()
        ->assertStatus(502)
        ->assertJsonPath('error', 'EPurchaseUnavailable');
});

it('requires authentication for the po detail', function () {
    $this->getJson('/api/v1/epurchase/pos/1277')
        ->assertStatus(401);
});

it('returns 401 EPurchaseSessionExpired when no company session is cached for the po detail', function () {
    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/epurchase/pos/1277')
        ->assertStatus(401)
        ->assertJsonPath('error', 'EPurchaseSessionExpired')
        ->assertJsonPath('statusCode', 401);
});

it('returns the line items of a company purchase order', function () {
    seedEpurchasePosSession($this->user);
    Http::fake(['*viewDetail*' => Http::response(epurchasePoDetailPayload())]);

    $response = getEpurchasePoDetail('1277')
        ->assertOk()
        ->assertJsonPath('data.0.id', 14628)
        ->assertJsonPath('data.0.prRefNum', 'PR-CEN-20260112-004')
        ->assertJsonPath('data.0.itemCode', 'PRO-SO0081')
        ->assertJsonPath('data.0.description', 'Black Refill Ink Marker (LETAO, 990 Plus, 50g)')
        ->assertJsonPath('data.0.description2', '')
        ->assertJsonPath('data.0.poDescription', null)
        ->assertJsonPath('data.0.qty', 2520)
        ->assertJsonPath('data.0.unit', 'BOTTLE')
        ->assertJsonPath('data.0.campusCode', 'CEN')
        ->assertJsonPath('data.0.divisionCode', 'Other')
        ->assertJsonPath('data.0.departmentCode', 'PROD')
        ->assertJsonPath('data.0.location', 'PROD CEN WH')
        ->assertJsonPath('data.0.unitCost', 0.4)
        ->assertJsonPath('data.0.deliveryFee', 0)
        ->assertJsonPath('data.0.discount', 0)
        ->assertJsonPath('data.0.vat', 0)
        ->assertJsonPath('data.0.usdAmount', 1008)
        ->assertJsonPath('data.0.purchaseQty', 0)
        ->assertJsonPath('data.0.cancelQty', 0)
        ->assertJsonPath('data.0.pendingQty', 2520)
        ->assertJsonPath('data.0.status', 'Pending')
        ->assertJsonPath('data.0.forceClose', 0)
        ->assertJsonPath('data.1.itemCode', 'STA-PEN-014')
        ->assertJsonPath('data.1.currency', 'KHR');

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), '/po-viewDetail/viewDetail')
            && $request->method() === 'GET'
            && $request->hasHeader('Authorization', 'Bearer company-session-jwt')
            && $request['po_id'] === '1277'
            && $request['getPODetailTable'] === '1';
    });

    expect($response->json('data'))->toHaveCount(2);
});

it('returns 404 for a non-numeric po id', function () {
    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/epurchase/pos/not-a-number')
        ->assertNotFound()
        ->assertJsonPath('statusCode', 404);
});

it('refreshes the company session and retries the po detail when the upstream rejects it once', function () {
    seedEpurchasePosSession($this->user);
    Http::fake([
        '*viewDetail*' => Http::sequence()
            ->push(['message' => 'Unauthenticated.'], 401)
            ->push(epurchasePoDetailPayload()),
        '*/default_user_access/refresh*' => Http::response([
            'access_token' => 'renewed-company-jwt',
            'token_type' => 'bearer',
            'expires_in' => 7200,
        ]),
    ]);

    getEpurchasePoDetail('1277')
        ->assertOk()
        ->assertJsonPath('data.0.itemCode', 'PRO-SO0081');

    expect(app(EPurchaseSessionService::class)->get($this->user->getAuthIdentifier())?->jwt)
        ->toBe('renewed-company-jwt');
});

it('returns 502 when the company system is unreachable for the po detail', function () {
    seedEpurchasePosSession($this->user);
    Http::fake(['*viewDetail*' => Http::failedConnection()]);

    getEpurchasePoDetail('1277')
        ->assertStatus(502)
        ->assertJsonPath('error', 'EPurchaseUnavailable');
});

it('returns 502 when the upstream po detail payload is not a list of lines', function () {
    seedEpurchasePosSession($this->user);
    Http::fake(['*viewDetail*' => Http::response(['result' => 'error'])]);

    getEpurchasePoDetail('1277')
        ->assertStatus(502)
        ->assertJsonPath('error', 'EPurchaseUnavailable');
});
