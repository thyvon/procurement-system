<?php

use App\Models\User;
use Illuminate\Testing\TestResponse;
use Modules\Organization\Models\Entity;
use Modules\PurchaseOrders\Models\Evaluation;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'staff'] as $role) {
        Role::findOrCreate($role, 'sanctum');
    }

    $this->entity = Entity::create(['code' => 'ENT-EV', 'name' => 'Entity EV', 'timezone' => 'UTC', 'locale' => 'en']);
    $this->otherEntity = Entity::create(['code' => 'ENT-EV2', 'name' => 'Entity EV2', 'timezone' => 'UTC', 'locale' => 'en']);

    $this->admin = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->admin->assignRole('admin');

    $this->staff = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->staff->assignRole('staff');
});

function evaluationPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'recommendation_basis' => 'Best value for money.',
        'items' => [
            ['item_code' => 'ITM-1', 'description' => 'A4 Paper', 'qty' => 10, 'uom' => 'Box'],
            ['item_code' => 'ITM-2', 'description' => 'Stapler', 'qty' => 2, 'uom' => 'Pcs'],
        ],
        'quotations' => [
            [
                'supplier_code' => 'SUP-00055',
                'supplier_name' => 'Kuy Leng',
                'supplier_phone' => '012 876 676',
                'supplier_address' => '120 Street 271, Phnom Penh',
                'discount' => 5,
                'vat' => 10,
                'price' => 'Good',
                'quality' => 'A',
                'lead_time' => '3 days',
                'warranty' => '12 months',
                'payment_terms' => '30 days',
                'other_remarks' => 'Deliver within 7 days.',
                'lines' => [
                    ['item_index' => 0, 'brand' => 'Double A', 'unit_cost' => 3.5, 'is_selected' => true],
                    ['item_index' => 1, 'brand' => 'Max', 'unit_cost' => 5, 'is_selected' => false],
                ],
            ],
            [
                'supplier_code' => 'SUP-00056',
                'supplier_name' => 'Acme Trading',
                'supplier_phone' => '011 222 333',
                'supplier_address' => '45 Monitor St, Battambang',
                'discount' => 0,
                'vat' => 0,
                'price' => 'Fair',
                'quality' => 'B',
                'lead_time' => '5 days',
                'warranty' => '6 months',
                'payment_terms' => 'Cash',
                'lines' => [
                    ['item_index' => 0, 'brand' => 'Copywell', 'unit_cost' => 4, 'is_selected' => false],
                    ['item_index' => 1, 'brand' => 'Max', 'unit_cost' => 4.5, 'is_selected' => true],
                ],
            ],
        ],
    ], $overrides);
}

function storeEvaluation(array $overrides = [], ?User $user = null): TestResponse
{
    $user ??= test()->admin;

    return test()->actingAs($user, 'sanctum')
        ->postJson('/api/v1/purchase-orders/evaluations', evaluationPayload($overrides));
}

it('requires authentication', function () {
    $this->getJson('/api/v1/purchase-orders/evaluations')->assertStatus(401);
});

it('creates an evaluation with a server-generated code and recomputed totals', function () {
    $response = storeEvaluation()->assertStatus(201);

    expect($response->json('data.code'))->toMatch('/^EVAL-\d{2}-\d{3}$/');

    $response
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.recommendationBasis', 'Best value for money.')
        ->assertJsonPath('data.createdBy', $this->admin->name)
        // JSON drops the .0 fraction (no JSON_PRESERVE_ZERO_FRACTION) — 44.00 encodes as 44.
        // awarded = (10 × 3.5 selected) + (2 × 4.5 selected) = 44.00
        ->assertJsonPath('data.awardedTotal', 44)
        ->assertJsonCount(2, 'data.items')
        ->assertJsonCount(2, 'data.quotations')
        ->assertJsonPath('data.quotations.0.supplierCode', 'SUP-00055')
        ->assertJsonPath('data.quotations.0.supplierAddress', '120 Street 271, Phnom Penh')
        // subtotal = 35 + 10 = 45; grand = 45 − 5 discount + 10 vat = 50
        ->assertJsonPath('data.quotations.0.subtotal', 45)
        ->assertJsonPath('data.quotations.0.grandTotal', 50)
        ->assertJsonCount(2, 'data.quotations.0.lines')
        ->assertJsonPath('data.quotations.0.lines.0.lineTotal', 35)
        ->assertJsonPath('data.quotations.0.lines.0.isSelected', true)
        ->assertJsonPath('data.quotations.1.subtotal', 49)
        ->assertJsonPath('data.quotations.1.grandTotal', 49);

    // Suppliers column = winners (each quotation owns at least one ticked line).
    expect(collect($response->json('data.suppliers'))->pluck('code')->all())
        ->toBe(['SUP-00055', 'SUP-00056']);

    expect(Evaluation::query()->count())->toBe(1);
});

it('forbids staff from creating evaluations', function () {
    storeEvaluation([], $this->staff)->assertStatus(403);

    expect(Evaluation::query()->count())->toBe(0);
});

it('lets staff list evaluations with pagination meta', function () {
    storeEvaluation();

    $response = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/purchase-orders/evaluations')
        ->assertOk()
        ->assertJsonPath('meta.page', 1)
        ->assertJsonPath('meta.perPage', 20)
        ->assertJsonPath('meta.total', 1);

    expect($response->json('data.0.awardedTotal'))->toEqual(44)
        ->and($response->json('data.0.status'))->toBe('draft');
});

it('pages the list with page and per_page', function () {
    storeEvaluation();
    storeEvaluation();

    $page1 = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/purchase-orders/evaluations?per_page=1&page=1')
        ->assertOk()
        ->assertJsonPath('meta.page', 1)
        ->assertJsonPath('meta.perPage', 1)
        ->assertJsonPath('meta.total', 2)
        ->json('data.*.code');

    $page2 = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/purchase-orders/evaluations?per_page=1&page=2')
        ->assertOk()
        ->assertJsonPath('meta.page', 2)
        ->json('data.*.code');

    expect($page1)->toHaveCount(1)
        ->and($page2)->toHaveCount(1)
        ->and(array_intersect($page1, $page2))->toBe([]);
});

it('searches the list by code and supplier', function () {
    storeEvaluation();

    $byCode = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/purchase-orders/evaluations?search=EVAL')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->json('data.*.code');

    $bySupplier = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/purchase-orders/evaluations?search=Acme')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->json('data.*.code');

    $noHit = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/purchase-orders/evaluations?search=NoSuchSupplier')
        ->assertOk()
        ->assertJsonPath('meta.total', 0);

    expect($byCode)->toHaveCount(1)
        ->and($bySupplier)->toHaveCount(1)
        ->and($noHit->json('data'))->toBeEmpty();
});

it('shows an evaluation with items, quotations and lines', function () {
    $id = storeEvaluation()->json('data.id');

    $response = $this->actingAs($this->staff, 'sanctum')
        ->getJson("/api/v1/purchase-orders/evaluations/{$id}")
        ->assertOk()
        ->assertJsonPath('data.id', $id)
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.items.0.itemCode', 'ITM-1')
        ->assertJsonPath('data.items.0.qty', 10)
        ->assertJsonPath('data.items.0.uom', 'Box')
        ->assertJsonCount(2, 'data.quotations')
        ->assertJsonCount(2, 'data.quotations.0.lines')
        ->assertJsonPath('data.quotations.0.lines.0.unitCost', 3.5)
        ->assertJsonPath('data.quotations.0.price', 'Good')
        ->assertJsonPath('data.quotations.0.paymentTerms', '30 days')
        ->assertJsonPath('data.quotations.0.otherRemarks', 'Deliver within 7 days.');

    // Lines link back to items so the client can rebuild the matrix.
    $itemId = $response->json('data.items.0.id');
    expect($response->json('data.quotations.0.lines.0.itemId'))->toBe($itemId);
});

it('updates an evaluation by replacing the matrix and recomputing totals', function () {
    $id = storeEvaluation()->json('data.id');

    $payload = evaluationPayload();
    // Swap the winners: quotation 1 wins both lines now.
    $payload['quotations'][0]['lines'][0]['is_selected'] = false;
    $payload['quotations'][1]['lines'][0]['is_selected'] = true;
    $payload['quotations'][1]['lines'][1]['is_selected'] = true;
    // Also raise one unit cost: item 1 from 4 to 6 on quotation 1.
    $payload['quotations'][1]['lines'][1]['unit_cost'] = 6;
    $payload['recommendation_basis'] = 'Updated basis.';

    $response = $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/purchase-orders/evaluations/{$id}", $payload)
        ->assertOk()
        ->assertJsonPath('data.recommendationBasis', 'Updated basis.')
        // awarded = (10 × 4) + (2 × 6) = 52.00
        ->assertJsonPath('data.awardedTotal', 52)
        ->assertJsonCount(2, 'data.items')
        ->assertJsonCount(2, 'data.quotations')
        ->assertJsonCount(2, 'data.quotations.0.lines');

    expect(collect($response->json('data.suppliers'))->pluck('code')->all())
        ->toBe(['SUP-00056']);

    // Wholesale replace: child rows were rebuilt, not duplicated.
    expect($this->actingAs($this->staff, 'sanctum')
        ->getJson("/api/v1/purchase-orders/evaluations/{$id}")
        ->assertOk()
        ->json('data.quotations'))->toHaveCount(2);
});

it('forbids staff from updating evaluations', function () {
    $id = storeEvaluation()->json('data.id');

    $this->actingAs($this->staff, 'sanctum')
        ->patchJson("/api/v1/purchase-orders/evaluations/{$id}", evaluationPayload())
        ->assertStatus(403);
});

it('soft-deletes an evaluation', function () {
    $id = storeEvaluation()->json('data.id');

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/purchase-orders/evaluations/{$id}")
        ->assertOk()
        ->assertJsonPath('data.deleted', true);

    expect($evaluation = Evaluation::withTrashed()->find($id))->not->toBeNull()
        ->and($evaluation->deleted_at)->not->toBeNull();

    $this->actingAs($this->staff, 'sanctum')
        ->getJson("/api/v1/purchase-orders/evaluations/{$id}")
        ->assertNotFound();
});

it('forbids staff from deleting evaluations', function () {
    $id = storeEvaluation()->json('data.id');

    $this->actingAs($this->staff, 'sanctum')
        ->deleteJson("/api/v1/purchase-orders/evaluations/{$id}")
        ->assertStatus(403);
});

it('rejects a single quotation with a 422 envelope', function () {
    $payload = evaluationPayload();
    $payload['quotations'] = [$payload['quotations'][0]];

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/purchase-orders/evaluations', $payload)
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);

    expect(Evaluation::query()->count())->toBe(0);
});

it('rejects an item without any selected winning line', function () {
    $payload = evaluationPayload();
    $payload['quotations'][0]['lines'][0]['is_selected'] = false;
    $payload['quotations'][1]['lines'][1]['is_selected'] = false;

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/purchase-orders/evaluations', $payload)
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422);

    expect($response->json('errors'))->toHaveKey('items.0')
        ->and($response->json('errors'))->toHaveKey('items.1');
});

it('rejects an item with more than one selected winning line', function () {
    $payload = evaluationPayload();
    // Item 0 is already won by quotation 0; mark quotation 1 as winner too.
    $payload['quotations'][1]['lines'][0]['is_selected'] = true;

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/purchase-orders/evaluations', $payload)
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422);

    expect($response->json('errors'))->toHaveKey('items.0');
});

it('rejects a quotation that does not price every item', function () {
    $payload = evaluationPayload();
    $payload['quotations'][0]['lines'] = [$payload['quotations'][0]['lines'][0]];

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/purchase-orders/evaluations', $payload)
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422);

    expect($response->json('errors'))->toHaveKey('quotations.0.lines');
});

it('rejects a duplicate supplier within the same evaluation', function () {
    $payload = evaluationPayload();
    $payload['quotations'][1]['supplier_code'] = $payload['quotations'][0]['supplier_code'];

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/purchase-orders/evaluations', $payload)
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422);

    expect($response->json('errors'))->toHaveKey('quotations.1.supplier_code');
});

it('rejects duplicate suppliers even when supplier codes differ only by case', function () {
    $payload = evaluationPayload();
    $payload['quotations'][1]['supplier_code'] = strtoupper($payload['quotations'][0]['supplier_code']);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/purchase-orders/evaluations', $payload)
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422);

    expect($response->json('errors'))->toHaveKey('quotations.1.supplier_code');
});

it('rejects duplicate item lines within a quotation', function () {
    $payload = evaluationPayload();
    $payload['quotations'][0]['lines'][] = $payload['quotations'][0]['lines'][0];

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/purchase-orders/evaluations', $payload)
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422);

    expect($response->json('errors'))->toHaveKey('quotations.0.lines');
});

it('rejects a line referencing an unknown item index', function () {
    $payload = evaluationPayload();
    $payload['quotations'][0]['lines'][0]['item_index'] = 99;

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/purchase-orders/evaluations', $payload)
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422);

    expect($response->json('errors'))->toHaveKey('quotations.0.lines.0.item_index');
});

it('rejects missing required quotation supplier fields', function () {
    $payload = evaluationPayload();
    unset($payload['quotations'][1]['supplier_code']);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/purchase-orders/evaluations', $payload)
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonValidationErrors(['quotations.1.supplier_code']);
});

it('rejects a quotation without an address or phone', function () {
    $payload = evaluationPayload();
    unset($payload['quotations'][0]['supplier_address'], $payload['quotations'][1]['supplier_phone']);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/purchase-orders/evaluations', $payload)
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonValidationErrors(['quotations.0.supplier_address', 'quotations.1.supplier_phone']);

    expect(Evaluation::query()->count())->toBe(0);
});

it('rejects a blank recommendation basis', function () {
    $payload = evaluationPayload();
    $payload['recommendation_basis'] = '';

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/purchase-orders/evaluations', $payload)
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonValidationErrors(['recommendation_basis']);
});

it('rejects a blank item description', function () {
    $payload = evaluationPayload();
    $payload['items'][0]['description'] = '   ';

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/purchase-orders/evaluations', $payload)
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonValidationErrors(['items.0.description']);
});

it('filters the list by status', function () {
    $approvedId = storeEvaluation()->json('data.id');
    Evaluation::query()->whereKey($approvedId)->update(['status' => 'approved']);
    $draftId = storeEvaluation()->json('data.id');

    $approved = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/purchase-orders/evaluations?status=approved')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $approvedId);

    $drafts = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/purchase-orders/evaluations?status=draft')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $draftId);

    $none = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/purchase-orders/evaluations?status=rejected')
        ->assertOk()
        ->assertJsonPath('meta.total', 0);

    expect($approved->json('data.0.status'))->toBe('approved')
        ->and($drafts->json('data.0.status'))->toBe('draft')
        ->and($none->json('data'))->toBeEmpty();
});

it('rejects an unknown status filter with a 422 envelope', function () {
    $response = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/purchase-orders/evaluations?status=unknown')
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);

    expect($response->json('errors'))->toHaveKey('status');
});

it('cannot be modified once an approval has frozen it', function (string $status) {
    $id = storeEvaluation()->json('data.id');
    Evaluation::query()->whereKey($id)->update(['status' => $status]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/purchase-orders/evaluations/{$id}", evaluationPayload())
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422);

    expect($response->json('errors.status.0'))
        ->toBe("An evaluation with status '{$status}' cannot be modified.")
        ->and(Evaluation::query()->find($id)->status)->toBe($status);
})->with(['in_review', 'approved', 'rejected']);

it('never returns other entities evaluations', function () {
    $foreignAdmin = User::factory()->create(['entity_id' => $this->otherEntity->getKey()]);
    $foreignAdmin->assignRole('admin');

    $foreign = storeEvaluation([], $foreignAdmin);
    $foreignId = $foreign->json('data.id');
    $foreignCode = $foreign->json('data.code');

    $codes = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/purchase-orders/evaluations')
        ->assertOk()
        ->json('data.*.code');

    expect($codes)->not->toContain($foreignCode);

    $this->actingAs($this->staff, 'sanctum')
        ->getJson("/api/v1/purchase-orders/evaluations/{$foreignId}")
        ->assertNotFound();
});
