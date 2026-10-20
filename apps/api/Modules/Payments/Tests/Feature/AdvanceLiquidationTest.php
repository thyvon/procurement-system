<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Modules\Organization\Models\Entity;
use Modules\Payments\Models\AdvanceLiquidation;
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

    // The code carries a Ym segment: freeze June 2026 so LAD-CEN-202606-0001
    // is deterministic whether the service derives it from `liquidation_date` or now().
    $this->travelTo(Carbon::parse('2026-06-15 09:00:00'));
});

function liquidationLine(array $overrides = []): array
{
    return array_replace_recursive([
        'description' => 'Taxi fare',
        'currency' => 'USD',
        'amount' => 220,
    ], $overrides);
}

function liquidationPayload(string $cashRequestId, array $overrides = []): array
{
    return array_replace_recursive([
        'cash_request_id' => $cashRequestId,
        'liquidation_date' => '2026-06-15',
        'exchange_rate' => 4026,
        'campus' => 'CEN',
        'division' => 'FIN',
        'department' => 'ACC',
        'lines' => [
            liquidationLine(),
            liquidationLine(['description' => 'Hotel', 'currency' => 'KHR', 'amount' => 1500000]),
        ],
    ], $overrides);
}

function advanceSource(array $overrides = []): CashRequest
{
    return CashRequest::factory()->advance()->approved()->create(array_merge([
        'entity_id' => test()->entity->getKey(),
        'created_by' => test()->admin->getKey(),
        'total_usd' => 600,
    ], $overrides));
}

function storeAdvanceLiquidation(string $cashRequestId, array $overrides = [], ?User $user = null): TestResponse
{
    $user ??= test()->admin;

    return test()->actingAs($user, 'sanctum')
        ->postJson('/api/v1/payments/advance-liquidations', liquidationPayload($cashRequestId, $overrides));
}

it('requires authentication', function () {
    $this->getJson('/api/v1/payments/advance-liquidations')->assertStatus(401);
});

it('forbids staff from creating advance liquidations but lets them list', function () {
    storeAdvanceLiquidation(advanceSource()->getKey(), [], $this->staff)->assertStatus(403);

    expect(AdvanceLiquidation::query()->count())->toBe(0);

    $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/payments/advance-liquidations')
        ->assertOk()
        ->assertJsonPath('meta.page', 1)
        ->assertJsonPath('meta.perPage', 20)
        ->assertJsonPath('meta.total', 0);
});

it('requires the source cash request to be approved', function () {
    $draft = CashRequest::factory()->advance()->create([
        'entity_id' => $this->entity->getKey(),
        'created_by' => $this->admin->getKey(),
        'total_usd' => 600,
    ]);

    $response = storeAdvanceLiquidation($draft->getKey())
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors' => ['cash_request_id']]);

    expect(AdvanceLiquidation::query()->count())->toBe(0);
});

it('refuses to liquidate a source that is not an advance', function () {
    $petty = CashRequest::factory()->approved()->create([
        'entity_id' => $this->entity->getKey(),
        'created_by' => $this->admin->getKey(),
        'total_usd' => 500,
    ]);

    $response = storeAdvanceLiquidation($petty->getKey())
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors' => ['cash_request_id']]);

    expect($response->json('errors.cash_request_id'))->not->toBeEmpty()
        ->and(AdvanceLiquidation::query()->count())->toBe(0);
});

it('liquidates an approved advance with the derived usd totals and code', function () {
    $source = advanceSource(['total_usd' => 600]);

    $response = storeAdvanceLiquidation($source->getKey())->assertStatus(201);

    $response
        ->assertJsonStructure([
            'data' => [
                'id',
                'code',
                'status',
                'cashRequestId',
                'totalActualExpenseUsd',
                'totalCashAdvanceUsd',
                'remainingCashUsd',
                'lines',
            ],
        ])
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.cashRequestId', $source->getKey())
        ->assertJsonPath('data.cashRequestCode', $source->code)
        ->assertJsonPath('data.campus', 'CEN')
        ->assertJsonPath('data.liquidationDate', '2026-06-15')
        ->assertJsonPath('data.exchangeRate', 4026)
        // 220 + 1,500,000 ÷ 4026 = 592.58; 600 − 592.58 = 7.42.
        ->assertJsonPath('data.totalActualExpenseUsd', 592.58)
        ->assertJsonPath('data.totalCashAdvanceUsd', 600)
        ->assertJsonPath('data.remainingCashUsd', 7.42)
        ->assertJsonCount(2, 'data.lines')
        ->assertJsonPath('data.lines.0.amountUsd', 220)
        ->assertJsonPath('data.lines.1.amountUsd', 372.58);

    expect($response->json('data.code'))->toBe('LAD-CEN-202606-0001')
        ->and(AdvanceLiquidation::query()->count())->toBe(1);
});

it('reports a negative remaining balance when the expense exceeds the advance', function () {
    $source = advanceSource(['total_usd' => 100]);

    $payload = liquidationPayload($source->getKey(), ['exchange_rate' => 1]);
    $payload['lines'] = [liquidationLine(['description' => 'Taxi fare', 'amount' => 220])];

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/payments/advance-liquidations', $payload)
        ->assertStatus(201)
        ->assertJsonPath('data.totalActualExpenseUsd', 220)
        ->assertJsonPath('data.totalCashAdvanceUsd', 100)
        ->assertJsonPath('data.remainingCashUsd', -120);

    $liquidation = AdvanceLiquidation::query()->findOrFail($response->json('data.id'));

    expect((float) $liquidation->remaining_cash_usd)->toBe(-120.0);
});

it('updates a draft by replacing the whole line list and recomputing totals', function () {
    $source = advanceSource(['total_usd' => 600]);
    $id = storeAdvanceLiquidation($source->getKey())->json('data.id');

    $payload = liquidationPayload($source->getKey(), ['division' => 'OPS']);
    $payload['lines'] = [liquidationLine(['description' => 'Printer toner', 'amount' => 50])];

    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/payments/advance-liquidations/{$id}", $payload)
        ->assertOk()
        ->assertJsonPath('data.division', 'OPS')
        ->assertJsonCount(1, 'data.lines')
        ->assertJsonPath('data.lines.0.description', 'Printer toner')
        ->assertJsonPath('data.totalActualExpenseUsd', 50)
        ->assertJsonPath('data.totalCashAdvanceUsd', 600)
        ->assertJsonPath('data.remainingCashUsd', 550);

    $liquidation = AdvanceLiquidation::query()->with('lines')->findOrFail($id);

    expect($liquidation->lines)->toHaveCount(1)
        ->and((float) $liquidation->total_actual_expense_usd)->toBe(50.0)
        ->and((float) $liquidation->remaining_cash_usd)->toBe(550.0);
});

it('cancels a draft and blocks deleting it afterwards', function () {
    $source = advanceSource();
    $id = storeAdvanceLiquidation($source->getKey())->json('data.id');

    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/payments/advance-liquidations/{$id}/status", ['status' => 'cancelled'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $response = $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/payments/advance-liquidations/{$id}")
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);

    expect($response->json('errors'))->toHaveKey('status')
        ->and(AdvanceLiquidation::query()->find($id)->status)->toBe('cancelled')
        ->and(AdvanceLiquidation::onlyTrashed()->find($id))->toBeNull();
});

it('blocks deleting an advance liquidation that is no longer a draft', function () {
    $id = AdvanceLiquidation::factory()->approved()->create([
        'entity_id' => $this->entity->getKey(),
        'created_by' => $this->admin->getKey(),
    ])->getKey();

    $response = $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/payments/advance-liquidations/{$id}")
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);

    expect($response->json('errors'))->toHaveKey('status')
        ->and(AdvanceLiquidation::onlyTrashed()->find($id))->toBeNull();
});

it('never returns another entity advance liquidation', function () {
    $foreignAdmin = User::factory()->create(['entity_id' => $this->otherEntity->getKey()]);
    $foreignAdmin->assignRole('admin');

    $ownSource = advanceSource();
    $ownCode = storeAdvanceLiquidation($ownSource->getKey())
        ->assertStatus(201)
        ->json('data.code');

    $foreignSource = CashRequest::factory()->advance()->approved()->create([
        'entity_id' => $this->otherEntity->getKey(),
        'created_by' => $foreignAdmin->getKey(),
        'total_usd' => 600,
    ]);

    // Codes are entity-scoped: another campus keeps the foreign code apart
    // from ours.
    $foreign = storeAdvanceLiquidation($foreignSource->getKey(), ['campus' => 'MTT'], $foreignAdmin)
        ->assertStatus(201);
    $foreignId = $foreign->json('data.id');

    $rows = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/payments/advance-liquidations')
        ->assertOk()
        ->json('data');

    expect(array_column($rows, 'code'))->toContain($ownCode)
        ->not->toContain($foreign->json('data.code'))
        ->and(array_column($rows, 'cashRequestCode'))->toContain($ownSource->code);

    $this->actingAs($this->staff, 'sanctum')
        ->getJson("/api/v1/payments/advance-liquidations/{$foreignId}")
        ->assertNotFound();
});
