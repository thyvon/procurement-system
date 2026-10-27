<?php

use App\Models\User;
use Illuminate\Testing\TestResponse;
use Modules\Organization\Models\Entity;
use Modules\Payments\Models\CashClearance;
use Modules\Payments\Models\CashRequest;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'staff'] as $role) {
        Role::findOrCreate($role, 'sanctum');
    }

    $this->entity = Entity::create(['code' => 'ENT-PAY', 'name' => 'Entity PAY', 'timezone' => 'UTC', 'locale' => 'en']);
    $this->otherEntity = Entity::create(['code' => 'ENT-PAY2', 'name' => 'Entity PAY2', 'timezone' => 'UTC', 'locale' => 'en']);

    $this->admin = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->admin->assignRole('admin');

    $this->staff = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->staff->assignRole('staff');
});

function clearanceLine(array $overrides = []): array
{
    return array_replace_recursive([
        'invoice_date' => '2026-06-15',
        'description' => 'A4 Paper',
        'vendor' => 'Acme Trading',
        'campus' => 'CEN',
        'division' => 'FIN',
        'department' => 'ACC',
        'uom' => 'box',
        'qty' => 10,
        'unit_price_usd' => 15,
        'discount_usd' => 4,
        'vat_usd' => 0,
    ], $overrides);
}

function clearancePayload(string $cashRequestId, array $overrides = []): array
{
    return array_replace_recursive([
        'cash_request_id' => $cashRequestId,
        'clearance_date' => '2026-06-15',
        'exchange_rate' => 1,
        'lines' => [clearanceLine()],
    ], $overrides);
}

function approvedCashRequest(array $overrides = []): CashRequest
{
    return CashRequest::factory()->approved()->create(array_merge([
        'entity_id' => test()->entity->getKey(),
        'created_by' => test()->admin->getKey(),
        'total_usd' => 500,
    ], $overrides));
}

function storeCashClearance(string $cashRequestId, array $overrides = [], ?User $user = null): TestResponse
{
    $user ??= test()->admin;

    return test()->actingAs($user, 'sanctum')
        ->postJson('/api/v1/payments/cash-clearances', clearancePayload($cashRequestId, $overrides));
}

it('requires authentication', function () {
    $this->getJson('/api/v1/payments/cash-clearances')->assertStatus(401);
});

it('forbids staff from creating cash clearances but lets them list', function () {
    $request = approvedCashRequest();

    storeCashClearance($request->getKey(), [], $this->staff)->assertStatus(403);

    expect(CashClearance::query()->count())->toBe(0);

    $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/payments/cash-clearances')
        ->assertOk()
        ->assertJsonPath('meta.page', 1)
        ->assertJsonPath('meta.perPage', 20)
        ->assertJsonPath('meta.total', 0);
});

it('requires the source cash request to be approved', function () {
    $draft = CashRequest::factory()->create([
        'entity_id' => $this->entity->getKey(),
        'created_by' => $this->admin->getKey(),
        'total_usd' => 500,
    ]);

    $response = storeCashClearance($draft->getKey())
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors' => ['cash_request_id']]);

    expect(CashClearance::query()->count())->toBe(0);
});

it('refuses to clear a source that is not petty cash', function () {
    $advance = CashRequest::factory()->advance()->approved()->create([
        'entity_id' => $this->entity->getKey(),
        'created_by' => $this->admin->getKey(),
        'total_usd' => 600,
    ]);

    $response = storeCashClearance($advance->getKey())
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors' => ['cash_request_id']]);

    expect($response->json('errors.cash_request_id'))->not->toBeEmpty()
        ->and(CashClearance::query()->count())->toBe(0);
});

it('creates a clearance against an approved request with recomputed totals', function () {
    $request = approvedCashRequest(['total_usd' => 500]);

    $response = storeCashClearance($request->getKey())->assertStatus(201);

    $response
        ->assertJsonStructure([
            'data' => ['id', 'code', 'status', 'cashRequestId', 'totalUsd', 'requestAmountUsd', 'balanceUsd', 'lines'],
        ])
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.cashRequestId', $request->getKey())
        ->assertJsonPath('data.cashRequestCode', $request->code)
        ->assertJsonPath('data.clearanceDate', '2026-06-15')
        // 10 × 15 − 4 + 0 = 146; 500 − 146 = 354.
        ->assertJsonPath('data.totalUsd', 146)
        ->assertJsonPath('data.requestAmountUsd', 500)
        ->assertJsonPath('data.balanceUsd', 354)
        ->assertJsonCount(1, 'data.lines')
        ->assertJsonPath('data.lines.0.amountUsd', 146);

    expect($response->json('data.code'))->toMatch('/^PCC-\d{6}-\d{4}$/')
        ->and(CashClearance::query()->count())->toBe(1);
});

it('includes vat and nets the discount in the line amount', function () {
    $request = approvedCashRequest(['total_usd' => 500]);

    $response = storeCashClearance($request->getKey(), [
        'lines' => [[
            'qty' => 10,
            'unit_price_usd' => 1,
            'discount_usd' => 1,
            'vat_usd' => 0,
        ]],
    ])->assertStatus(201);

    // 10 × 1 − 1 + 0 = 9; 500 − 9 = 491.
    $response
        ->assertJsonPath('data.lines.0.amountUsd', 9)
        ->assertJsonPath('data.totalUsd', 9)
        ->assertJsonPath('data.balanceUsd', 491);
});

it('blocks a clearance that would exceed its cash request amount', function () {
    $request = approvedCashRequest(['total_usd' => 100]);

    // Create path: the default line spends 146 against 100 approved.
    $response = storeCashClearance($request->getKey())
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors' => ['lines']]);

    expect($response->json('errors.lines.0'))
        ->toContain($request->code)
        ->toContain('146.00')
        ->toContain('100.00')
        ->and(CashClearance::query()->count())->toBe(0);

    // Update path: growing an existing draft past the amount rolls back.
    $id = storeCashClearance($request->getKey(), [
        'lines' => [clearanceLine(['qty' => 4, 'unit_price_usd' => 25, 'discount_usd' => 0])],
    ])
        ->assertStatus(201)
        ->json('data.id');

    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/payments/cash-clearances/{$id}", clearancePayload($request->getKey()))
        ->assertStatus(422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors' => ['lines']]);

    expect((float) CashClearance::query()->findOrFail($id)->total_usd)->toBe(100.0);
});

it('allows a clearance that exactly consumes the request amount', function () {
    $request = approvedCashRequest(['total_usd' => 146]);

    storeCashClearance($request->getKey())
        ->assertStatus(201)
        ->assertJsonPath('data.totalUsd', 146)
        ->assertJsonPath('data.balanceUsd', 0);
});

it('allows only one final clearance per cash request until the first is deleted', function () {
    $request = approvedCashRequest(['total_usd' => 500]);

    $first = storeCashClearance($request->getKey())->assertStatus(201);

    storeCashClearance($request->getKey())
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors' => ['cash_request_id']]);

    expect(CashClearance::query()->count())->toBe(1);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/payments/cash-clearances/{$first->json('data.id')}")
        ->assertOk()
        ->assertJsonPath('data.deleted', true);

    storeCashClearance($request->getKey())->assertStatus(201);

    expect(CashClearance::query()->count())->toBe(1)
        ->and(CashClearance::onlyTrashed()->count())->toBe(1);
});

it('updates a draft clearance by replacing the lines and recomputing totals', function () {
    $request = approvedCashRequest(['total_usd' => 500]);
    $id = storeCashClearance($request->getKey())->json('data.id');

    $payload = clearancePayload($request->getKey(), ['clearance_date' => '2026-06-20']);
    $payload['lines'] = [clearanceLine([
        'description' => 'Stapler',
        'qty' => 3,
        'unit_price_usd' => 10,
        'discount_usd' => 0,
        'vat_usd' => 0,
    ])];

    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/payments/cash-clearances/{$id}", $payload)
        ->assertOk()
        ->assertJsonPath('data.clearanceDate', '2026-06-20')
        ->assertJsonCount(1, 'data.lines')
        ->assertJsonPath('data.lines.0.description', 'Stapler')
        ->assertJsonPath('data.lines.0.amountUsd', 30)
        ->assertJsonPath('data.totalUsd', 30)
        ->assertJsonPath('data.balanceUsd', 470);

    $clearance = CashClearance::query()->with('lines')->findOrFail($id);

    expect($clearance->lines)->toHaveCount(1)
        ->and((float) $clearance->total_usd)->toBe(30.0)
        ->and((float) $clearance->balance_usd)->toBe(470.0);
});

it('re-runs the approved-source and one-clearance guards on update', function () {
    $first = approvedCashRequest(['total_usd' => 500]);
    $second = approvedCashRequest(['total_usd' => 700]);
    $draftSource = CashRequest::factory()->create([
        'entity_id' => $this->entity->getKey(),
        'created_by' => $this->admin->getKey(),
        'total_usd' => 300,
    ]);

    $id = storeCashClearance($first->getKey())->json('data.id');

    // Repointing onto an approved request that already has a clearance.
    storeCashClearance($second->getKey())->assertStatus(201);

    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/payments/cash-clearances/{$id}", clearancePayload($second->getKey()))
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors' => ['cash_request_id']]);

    // Repointing onto an unapproved source is refused on update too.
    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/payments/cash-clearances/{$id}", clearancePayload($draftSource->getKey()))
        ->assertStatus(422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors' => ['cash_request_id']]);

    expect(CashClearance::query()->findOrFail($id)->cash_request_id)->toBe($first->getKey());
});

it('cancels a draft clearance and blocks deleting it afterwards', function () {
    $request = approvedCashRequest();
    $id = storeCashClearance($request->getKey())->json('data.id');

    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/payments/cash-clearances/{$id}/status", ['status' => 'cancelled'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $response = $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/payments/cash-clearances/{$id}")
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);

    expect($response->json('errors'))->toHaveKey('status')
        ->and(CashClearance::query()->find($id)->status)->toBe('cancelled')
        ->and(CashClearance::onlyTrashed()->find($id))->toBeNull();
});

it('never returns another entity cash clearance', function () {
    $foreignAdmin = User::factory()->create(['entity_id' => $this->otherEntity->getKey()]);
    $foreignAdmin->assignRole('admin');

    $ownRequest = approvedCashRequest();
    $own = storeCashClearance($ownRequest->getKey())->assertStatus(201);
    $ownId = $own->json('data.id');
    $ownCode = $own->json('data.code');

    $foreignRequest = CashRequest::factory()->approved()->create([
        'entity_id' => $this->otherEntity->getKey(),
        'created_by' => $foreignAdmin->getKey(),
        'total_usd' => 500,
    ]);

    // Codes are entity-scoped and carry no campus segment, so another month
    // keeps the foreign code apart from ours.
    $foreign = storeCashClearance($foreignRequest->getKey(), ['clearance_date' => '2026-08-15'], $foreignAdmin)
        ->assertStatus(201);
    $foreignId = $foreign->json('data.id');

    $listed = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/payments/cash-clearances')
        ->assertOk()
        ->json('data');

    expect(array_column($listed, 'code'))->toContain($ownCode)
        ->and(array_column($listed, 'code'))->not->toContain($foreign->json('data.code'))
        ->and(array_column($listed, 'id'))->toContain($ownId)
        ->and(array_column($listed, 'id'))->not->toContain($foreignId)
        ->and(array_column($listed, 'cashRequestCode'))->toContain($ownRequest->code);

    $this->actingAs($this->staff, 'sanctum')
        ->getJson("/api/v1/payments/cash-clearances/{$foreignId}")
        ->assertNotFound();
});
