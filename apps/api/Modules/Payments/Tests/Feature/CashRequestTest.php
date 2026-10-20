<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Modules\Organization\Models\Entity;
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

    // The code carries a Ym segment: freeze June 2026 so PC-CEN-202606-0001
    // is deterministic whether the service derives it from `request_date` or now().
    $this->travelTo(Carbon::parse('2026-06-15 09:00:00'));
});

function cashRequestLine(array $overrides = []): array
{
    return array_replace_recursive([
        'description' => 'Office supplies',
        'currency' => 'USD',
        'amount' => 220,
    ], $overrides);
}

function cashRequestPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'type' => CashRequest::TYPE_PETTY_CASH,
        'campus' => 'CEN',
        'division' => 'FIN',
        'department' => 'ACC',
        'request_date' => '2026-06-15',
        'exchange_rate' => 4026,
        'remark' => 'June petty cash.',
        'lines' => [
            cashRequestLine(),
            cashRequestLine(['description' => 'Team lunch', 'currency' => 'KHR', 'amount' => 1500000]),
        ],
    ], $overrides);
}

function storeCashRequestTo(string $url, array $overrides = [], ?User $user = null): TestResponse
{
    $user ??= test()->admin;

    return test()->actingAs($user, 'sanctum')
        ->postJson($url, cashRequestPayload($overrides));
}

function storeCashRequest(array $overrides = [], ?User $user = null): TestResponse
{
    return storeCashRequestTo('/api/v1/payments/petty-cash-requests', $overrides, $user);
}

it('requires authentication', function () {
    $this->getJson('/api/v1/payments/petty-cash-requests')->assertStatus(401);
});

it('forbids staff from creating cash requests but lets them list', function () {
    storeCashRequest([], $this->staff)->assertStatus(403);

    expect(CashRequest::query()->count())->toBe(0);

    storeCashRequest();

    $response = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/payments/petty-cash-requests')
        ->assertOk()
        ->assertJsonPath('meta.page', 1)
        ->assertJsonPath('meta.perPage', 20)
        ->assertJsonPath('meta.total', 1);

    expect($response->json('data.0.status'))->toBe('draft')
        ->and($response->json('data.0.code'))->toBe('PC-CEN-202606-0001')
        ->and($response->json('data.0'))->toHaveKey('lines');
});

it('creates a petty cash request with a server-generated campus-aware code', function () {
    $response = storeCashRequest()->assertStatus(201);

    $response
        ->assertJsonStructure([
            'data' => ['id', 'code', 'status', 'type', 'totalUsd', 'lines'],
        ])
        ->assertJsonPath('data.code', 'PC-CEN-202606-0001')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.type', 'petty_cash')
        ->assertJsonPath('data.campus', 'CEN')
        ->assertJsonPath('data.division', 'FIN')
        ->assertJsonPath('data.department', 'ACC')
        ->assertJsonPath('data.requestDate', '2026-06-15')
        ->assertJsonPath('data.exchangeRate', 4026)
        ->assertJsonStructure(['data' => ['createdBy', 'createdByPosition']])
        ->assertJsonCount(2, 'data.lines');

    expect(CashRequest::query()->count())->toBe(1);
});

it('converts every line to usd through the header exchange rate', function () {
    $response = storeCashRequest()->assertStatus(201);

    // 220 USD + 1,500,000 KHR ÷ 4026 = 220 + 372.58 → 592.58.
    $response
        ->assertJsonPath('data.totalUsd', 592.58)
        ->assertJsonPath('data.lines.0.amount', 220)
        ->assertJsonPath('data.lines.0.amountUsd', 220)
        ->assertJsonPath('data.lines.1.amount', 1500000)
        ->assertJsonPath('data.lines.1.amountUsd', 372.58);

    $cashRequest = CashRequest::query()->with('lines')->findOrFail($response->json('data.id'));

    expect((float) $cashRequest->total_usd)->toBe(592.58)
        ->and((float) $cashRequest->lines[0]->amount_usd)->toBe(220.0)
        ->and((float) $cashRequest->lines[1]->amount_usd)->toBe(372.58);
});

it('ignores client-supplied code status and totals', function () {
    $response = storeCashRequest([
        'code' => 'FAKE',
        'status' => 'approved',
        'total_usd' => 999999,
    ])->assertStatus(201);

    expect($response->json('data.code'))->not->toBe('FAKE')
        ->and($response->json('data.code'))->toMatch('/^PC-CEN-202606-\d{4}$/')
        ->and($response->json('data.status'))->toBe('draft')
        ->and((float) $response->json('data.totalUsd'))->toBe(592.58);

    $cashRequest = CashRequest::query()->findOrFail($response->json('data.id'));

    expect((float) $cashRequest->total_usd)->toBe(592.58)
        ->and($cashRequest->status)->toBe('draft')
        ->and($cashRequest->code)->toMatch('/^PC-CEN-202606-\d{4}$/');
});

it('updates a draft by replacing the whole line list', function () {
    $id = storeCashRequest()->json('data.id');

    $payload = cashRequestPayload([
        'division' => 'OPS',
        'remark' => 'Updated June petty cash.',
    ]);
    $payload['lines'] = [cashRequestLine(['description' => 'Printer toner', 'amount' => 100])];

    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/payments/petty-cash-requests/{$id}", $payload)
        ->assertOk()
        ->assertJsonPath('data.division', 'OPS')
        ->assertJsonPath('data.remark', 'Updated June petty cash.')
        ->assertJsonCount(1, 'data.lines')
        ->assertJsonPath('data.lines.0.description', 'Printer toner')
        ->assertJsonPath('data.totalUsd', 100);

    $cashRequest = CashRequest::query()->with('lines')->findOrFail($id);

    expect($cashRequest->lines)->toHaveCount(1)
        ->and((float) $cashRequest->total_usd)->toBe(100.0);
});

it('cancels a draft and blocks any further status change', function () {
    $id = storeCashRequest()->json('data.id');

    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/payments/petty-cash-requests/{$id}/status", ['status' => 'cancelled'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $response = $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/payments/petty-cash-requests/{$id}/status", ['status' => 'cancelled'])
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);

    expect($response->json('errors'))->toHaveKey('status')
        ->and(CashRequest::query()->find($id)->status)->toBe('cancelled');
});

it('refuses to cancel an approved cash request', function () {
    $id = CashRequest::factory()->approved()->create([
        'entity_id' => $this->entity->getKey(),
        'created_by' => $this->admin->getKey(),
    ])->getKey();

    $response = $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/payments/petty-cash-requests/{$id}/status", ['status' => 'cancelled'])
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);

    expect($response->json('errors'))->toHaveKey('status')
        ->and(CashRequest::query()->find($id)->status)->toBe('approved');
});

it('soft-deletes a draft cash request', function () {
    $id = storeCashRequest()->json('data.id');

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/payments/petty-cash-requests/{$id}")
        ->assertOk()
        ->assertJsonPath('data.deleted', true);

    $cashRequest = CashRequest::withTrashed()->find($id);

    expect($cashRequest)->not->toBeNull()
        ->and($cashRequest->deleted_at)->not->toBeNull();

    $this->actingAs($this->staff, 'sanctum')
        ->getJson("/api/v1/payments/petty-cash-requests/{$id}")
        ->assertNotFound();
});

it('blocks deleting a cash request that is no longer a draft', function () {
    $id = CashRequest::factory()->approved()->create([
        'entity_id' => $this->entity->getKey(),
        'created_by' => $this->admin->getKey(),
    ])->getKey();

    $response = $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/payments/petty-cash-requests/{$id}")
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);

    expect($response->json('errors'))->toHaveKey('status')
        ->and(CashRequest::query()->find($id))->not->toBeNull()
        ->and(CashRequest::onlyTrashed()->find($id))->toBeNull();
});

it('forbids another admin from editing or deleting a draft they do not own', function () {
    $id = storeCashRequest()->json('data.id');

    $otherAdmin = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $otherAdmin->assignRole('admin');

    $this->actingAs($otherAdmin, 'sanctum')
        ->patchJson("/api/v1/payments/petty-cash-requests/{$id}", cashRequestPayload())
        ->assertStatus(403);

    $this->actingAs($otherAdmin, 'sanctum')
        ->deleteJson("/api/v1/payments/petty-cash-requests/{$id}")
        ->assertStatus(403);

    // The creator keeps both abilities.
    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/payments/petty-cash-requests/{$id}", cashRequestPayload(['remark' => 'Touched by the owner.']))
        ->assertOk()
        ->assertJsonPath('data.remark', 'Touched by the owner.');

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/payments/petty-cash-requests/{$id}")
        ->assertOk()
        ->assertJsonPath('data.deleted', true);
});

it('never returns another entity cash request', function () {
    $foreignAdmin = User::factory()->create(['entity_id' => $this->otherEntity->getKey()]);
    $foreignAdmin->assignRole('admin');

    $ownCode = storeCashRequest()->assertStatus(201)->json('data.code');
    // Codes are entity-scoped, so a foreign PC-CEN-202606-0001 would collide
    // with ours; give the foreign row another campus to keep the codes apart.
    $foreign = storeCashRequest(['campus' => 'MTT'], $foreignAdmin)->assertStatus(201);
    $foreignId = $foreign->json('data.id');

    $codes = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/payments/petty-cash-requests')
        ->assertOk()
        ->json('data.*.code');

    expect($codes)->toContain($ownCode)
        ->not->toContain($foreign->json('data.code'));

    $this->actingAs($this->staff, 'sanctum')
        ->getJson("/api/v1/payments/petty-cash-requests/{$foreignId}")
        ->assertNotFound();
});

it('scopes each branch to its own request type', function () {
    $advanceDraftId = storeCashRequestTo('/api/v1/payments/advance-requests', [
        'type' => 'advance',
        'advance_request_for' => 'June field visit',
    ])->json('data.id');

    $advanceCancelledId = storeCashRequestTo('/api/v1/payments/advance-requests', [
        'type' => 'advance',
        'advance_request_for' => 'June team building',
    ])->json('data.id');

    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/payments/advance-requests/{$advanceCancelledId}/status", ['status' => 'cancelled'])
        ->assertOk();

    $pettyId = storeCashRequest()->json('data.id');

    // The status filter still works — inside the branch's own type.
    $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/payments/advance-requests?status=draft')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $advanceDraftId)
        ->assertJsonPath('data.0.type', 'advance')
        ->assertJsonPath('data.0.status', 'draft');

    $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/payments/advance-requests')
        ->assertOk()
        ->assertJsonPath('meta.total', 2);

    $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/payments/petty-cash-requests')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $pettyId)
        ->assertJsonPath('data.0.type', 'petty_cash');
});

it('hides a document from every other branch', function () {
    $pettyId = storeCashRequest()->json('data.id');

    foreach (['advance-requests', 'cash-requests'] as $branch) {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/payments/{$branch}/{$pettyId}")
            ->assertNotFound();

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/payments/{$branch}/{$pettyId}/status", ['status' => 'cancelled'])
            ->assertNotFound();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/payments/{$branch}/{$pettyId}")
            ->assertNotFound();
    }

    expect(CashRequest::query()->count())->toBe(1);
});

it('rejects a payload whose type contradicts the branch', function () {
    $response = storeCashRequest([
        'type' => 'advance',
        'advance_request_for' => 'June field visit',
    ])
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors' => ['type']]);

    expect($response->json('errors.type'))->not->toBeEmpty()
        ->and(CashRequest::query()->count())->toBe(0);
});

it('creates an advance and a cash request under their own branches', function () {
    storeCashRequestTo('/api/v1/payments/advance-requests', [
        'type' => 'advance',
        'advance_request_for' => 'June field visit',
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.type', 'advance')
        ->assertJsonPath('data.code', 'AD-CEN-202606-0001');

    storeCashRequestTo('/api/v1/payments/cash-requests', ['type' => 'cash'])
        ->assertStatus(201)
        ->assertJsonPath('data.type', 'cash')
        ->assertJsonPath('data.code', 'CR-CEN-202606-0001');

    // The petty cash branch never sees them.
    $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/payments/petty-cash-requests')
        ->assertOk()
        ->assertJsonPath('meta.total', 0);
});
