<?php

use App\Models\User;
use Illuminate\Testing\TestResponse;
use Modules\Invoices\Models\Invoice;
use Modules\Organization\Models\Entity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'staff'] as $role) {
        Role::findOrCreate($role, 'sanctum');
    }

    $this->entity = Entity::create(['code' => 'ENT-INV', 'name' => 'Entity INV', 'timezone' => 'UTC', 'locale' => 'en']);
    $this->otherEntity = Entity::create(['code' => 'ENT-INV2', 'name' => 'Entity INV2', 'timezone' => 'UTC', 'locale' => 'en']);

    $this->admin = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->admin->assignRole('admin');

    $this->staff = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->staff->assignRole('staff');
});

function invoicePayload(array $overrides = []): array
{
    return array_replace_recursive([
        'invoice_number' => 'SUP-INV-1001',
        'supplier_name' => 'Acme Trading',
        'supplier_code' => 'SUP-00056',
        'invoice_date' => '2026-10-01',
        'due_date' => '2026-10-31',
        'vat_amount' => 10,
        'discount_amount' => 5,
        'notes' => 'October delivery.',
        'lines' => [
            ['description' => 'A4 Paper', 'qty' => 10, 'unit_price' => 3.5],
            ['description' => 'Stapler', 'qty' => 2, 'unit_price' => 5],
        ],
    ], $overrides);
}

function storeInvoice(array $overrides = [], ?User $user = null): TestResponse
{
    $user ??= test()->admin;

    return test()->actingAs($user, 'sanctum')
        ->postJson('/api/v1/invoices', invoicePayload($overrides));
}

it('requires authentication', function () {
    $this->getJson('/api/v1/invoices')->assertStatus(401);

    $invoice = Invoice::factory()->create([
        'entity_id' => $this->entity->getKey(),
        'created_by' => $this->admin->getKey(),
    ]);

    $this->getJson("/api/v1/invoices/{$invoice->getKey()}")->assertStatus(401);
});

it('creates an invoice with a server-generated code and recomputed totals', function () {
    $response = storeInvoice()->assertStatus(201);

    expect($response->json('data.code'))->toMatch('/^INV-\d{2}-\d{3}$/');

    $response
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.invoiceNumber', 'SUP-INV-1001')
        ->assertJsonPath('data.supplierName', 'Acme Trading')
        ->assertJsonPath('data.supplierCode', 'SUP-00056')
        ->assertJsonPath('data.invoiceDate', '2026-10-01')
        ->assertJsonPath('data.dueDate', '2026-10-31')
        ->assertJsonPath('data.currency', 'USD')
        ->assertJsonPath('data.exchangeRate', 1)
        ->assertJsonPath('data.notes', 'October delivery.')
        ->assertJsonPath('data.createdBy', $this->admin->name)
        // Lines: 10 × 3.5 = 35 and 2 × 5 = 10 → subtotal 45;
        // total = 45 − 5 discount + 10 vat = 50.
        ->assertJsonCount(2, 'data.lines')
        ->assertJsonPath('data.lines.0.lineTotal', 35)
        ->assertJsonPath('data.lines.0.grandTotalUsd', 35)
        ->assertJsonPath('data.lines.0.position', 0)
        ->assertJsonPath('data.lines.1.lineTotal', 10)
        ->assertJsonPath('data.lines.1.grandTotalUsd', 10)
        ->assertJsonPath('data.subtotal', 45)
        ->assertJsonPath('data.vatAmount', 10)
        ->assertJsonPath('data.discountAmount', 5)
        ->assertJsonPath('data.total', 50)
        ->assertJsonPath('data.grandTotalUsd', 50);

    expect(Invoice::query()->count())->toBe(1);
});

it('ignores client-supplied code status and totals', function () {
    $response = storeInvoice([
        'code' => 'INV-99-999',
        'status' => 'recorded',
        'subtotal' => 999,
        'total' => 999,
        'grand_total_usd' => 999,
        'lines' => [
            ['grand_total_usd' => 999],
        ],
    ])->assertStatus(201);

    expect($response->json('data.code'))->not->toBe('INV-99-999')
        ->and($response->json('data.status'))->toBe('draft')
        ->and((float) $response->json('data.total'))->toBe(50.0)
        ->and((float) $response->json('data.grandTotalUsd'))->toBe(50.0)
        ->and((float) $response->json('data.lines.0.grandTotalUsd'))->toBe(35.0);
});

it('applies per-line discounts to the line totals and the subtotal', function () {
    $response = storeInvoice([
        'discount_amount' => 5,
        'vat_amount' => 0,
        'lines' => [
            ['description' => 'A4 Paper', 'qty' => 10, 'unit_price' => 3.5, 'discount' => 5],
            ['description' => 'Stapler', 'qty' => 2, 'unit_price' => 5],
        ],
    ])->assertStatus(201);

    // Line 0: 35 gross − 5 discount = 30 net; line 1 has no discount → 10.
    // subtotal 40; total = 40 − 5 header discount + 0 vat = 35.
    $response
        ->assertJsonPath('data.lines.0.discount', 5)
        ->assertJsonPath('data.lines.0.lineTotal', 30)
        ->assertJsonPath('data.lines.1.discount', 0)
        ->assertJsonPath('data.subtotal', 40)
        ->assertJsonPath('data.discountAmount', 5)
        ->assertJsonPath('data.total', 35);

    $invoice = Invoice::query()->with('lines')->findOrFail($response->json('data.id'));

    expect((float) $invoice->lines->first()->discount)->toBe(5.0)
        ->and((float) $invoice->lines->first()->line_total)->toBe(30.0)
        ->and((float) $invoice->subtotal)->toBe(40.0)
        ->and((float) $invoice->total)->toBe(35.0);
});

it('rejects a line discount larger than the line amount', function () {
    $response = storeInvoice([
        'vat_amount' => 0,
        'lines' => [
            ['description' => 'A4 Paper', 'qty' => 10, 'unit_price' => 3.5, 'discount' => 99],
        ],
    ])->assertStatus(422)
        ->assertJsonPath('statusCode', 422);

    // Dotted field keys stay flat in the envelope's `errors` object.
    expect($response->json('errors')['lines.0.discount'][0])
        ->toBe('The discount cannot exceed the line amount.')
        ->and(Invoice::query()->count())->toBe(0);
});

it('rejects a negative line discount', function () {
    storeInvoice([
        'lines' => [
            ['description' => 'A4 Paper', 'qty' => 10, 'unit_price' => 3.5, 'discount' => -1],
        ],
    ])->assertStatus(422)
        ->assertJsonPath('statusCode', 422);
});

it('persists a KHR invoice with its exchange rate', function () {
    $response = storeInvoice([
        'currency' => 'KHR',
        'exchange_rate' => 4100,
    ])->assertStatus(201);

    $response
        ->assertJsonPath('data.currency', 'KHR')
        ->assertJsonPath('data.exchangeRate', 4100);

    $invoice = Invoice::query()->findOrFail($response->json('data.id'));

    expect($invoice->currency)->toBe('KHR')
        ->and((float) $invoice->exchange_rate)->toBe(4100.0);
});

it('stores the USD equivalent on the head and each line', function () {
    $response = storeInvoice([
        'currency' => 'KHR',
        'exchange_rate' => 4100,
        'vat_amount' => 41000,
        'discount_amount' => 0,
        'lines' => [
            ['description' => 'A4 Paper', 'qty' => 10, 'unit_price' => 41000],
            ['description' => 'Stapler', 'qty' => 2, 'unit_price' => 20500],
        ],
    ])->assertStatus(201);

    // Lines: 410,000 + 41,000 KHR → 100 + 10 USD at 4100.
    // Header: 451,000 − 0 + 41,000 vat = 492,000 KHR → 120 USD.
    $response
        ->assertJsonPath('data.subtotal', 451000)
        ->assertJsonPath('data.total', 492000)
        ->assertJsonPath('data.lines.0.grandTotalUsd', 100)
        ->assertJsonPath('data.lines.1.grandTotalUsd', 10)
        ->assertJsonPath('data.grandTotalUsd', 120);

    $invoice = Invoice::query()->with('lines')->findOrFail($response->json('data.id'));

    expect((float) $invoice->grand_total_usd)->toBe(120.0)
        ->and((float) $invoice->lines[0]->grand_total_usd)->toBe(100.0)
        ->and((float) $invoice->lines[1]->grand_total_usd)->toBe(10.0);
});

it('requires an exchange rate when the currency is KHR', function () {
    storeInvoice(['currency' => 'KHR'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['exchange_rate']);
});

it('rejects an unknown currency code', function () {
    storeInvoice(['currency' => 'EUR', 'exchange_rate' => 1])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['currency']);
});

it('rejects an invoice without lines with a 422 envelope', function () {
    $payload = invoicePayload();
    $payload['lines'] = [];

    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/invoices', $payload)
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);

    expect($response->json('errors'))->toHaveKey('lines')
        ->and(Invoice::query()->count())->toBe(0);
});

it('rejects a line with a non-positive quantity', function () {
    $response = storeInvoice([
        'lines' => [
            ['description' => 'A4 Paper', 'qty' => 0, 'unit_price' => 3.5],
            ['description' => 'Stapler', 'qty' => 2, 'unit_price' => 5],
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['lines.0.qty']);

    expect($response->json('errors'))->toHaveKey('lines.0.qty');
});

it('rejects a blank invoice number or supplier name', function () {
    storeInvoice(['invoice_number' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['invoice_number']);

    storeInvoice(['supplier_name' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['supplier_name']);
});

it('rejects a due date before the invoice date', function () {
    storeInvoice(['due_date' => '2026-09-01'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['due_date']);
});

it('forbids staff from creating invoices', function () {
    storeInvoice([], $this->staff)->assertStatus(403);

    expect(Invoice::query()->count())->toBe(0);
});

it('lets staff list invoices with pagination meta', function () {
    storeInvoice();

    $response = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/invoices')
        ->assertOk()
        ->assertJsonPath('meta.page', 1)
        ->assertJsonPath('meta.perPage', 20)
        ->assertJsonPath('meta.total', 1);

    expect($response->json('data.0.status'))->toBe('draft')
        ->and((float) $response->json('data.0.total'))->toBe(50.0)
        ->and($response->json('data.0'))->toHaveKey('lines');
});

it('pages the list with page and per_page', function () {
    storeInvoice();
    storeInvoice(['invoice_number' => 'SUP-INV-1002']);

    $page1 = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/invoices?per_page=1&page=1')
        ->assertOk()
        ->assertJsonPath('meta.page', 1)
        ->assertJsonPath('meta.perPage', 1)
        ->assertJsonPath('meta.total', 2)
        ->json('data.*.code');

    $page2 = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/invoices?per_page=1&page=2')
        ->assertOk()
        ->assertJsonPath('meta.page', 2)
        ->json('data.*.code');

    expect($page1)->toHaveCount(1)
        ->and($page2)->toHaveCount(1)
        ->and(array_intersect($page1, $page2))->toBe([]);
});

it('searches the list by code, invoice number and supplier', function () {
    storeInvoice();

    $byCode = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/invoices?search=INV')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->json('data.*.code');

    $byInvoiceNumber = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/invoices?search=SUP-INV-1001')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->json('data.*.code');

    $bySupplier = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/invoices?search=Acme')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->json('data.*.code');

    $noHit = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/invoices?search=NoSuchSupplier')
        ->assertOk()
        ->assertJsonPath('meta.total', 0);

    expect($byCode)->toHaveCount(1)
        ->and($byInvoiceNumber)->toHaveCount(1)
        ->and($bySupplier)->toHaveCount(1)
        ->and($noHit->json('data'))->toBeEmpty();
});

it('filters the list by status', function () {
    $recordedId = storeInvoice()->json('data.id');
    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/invoices/{$recordedId}/status", ['status' => 'recorded'])
        ->assertOk();
    $draftId = storeInvoice(['invoice_number' => 'SUP-INV-1002'])->json('data.id');

    $recorded = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/invoices?status=recorded')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $recordedId);

    $drafts = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/invoices?status=draft')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $draftId);

    $none = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/invoices?status=cancelled')
        ->assertOk()
        ->assertJsonPath('meta.total', 0);

    expect($recorded->json('data.0.status'))->toBe('recorded')
        ->and($drafts->json('data.0.status'))->toBe('draft')
        ->and($none->json('data'))->toBeEmpty();
});

it('rejects an unknown status filter with a 422 envelope', function () {
    $response = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/invoices?status=unknown')
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);

    expect($response->json('errors'))->toHaveKey('status');
});

it('shows an invoice with its lines', function () {
    $id = storeInvoice()->json('data.id');

    $response = $this->actingAs($this->staff, 'sanctum')
        ->getJson("/api/v1/invoices/{$id}")
        ->assertOk()
        ->assertJsonPath('data.id', $id)
        ->assertJsonPath('data.invoiceNumber', 'SUP-INV-1001')
        ->assertJsonCount(2, 'data.lines')
        ->assertJsonPath('data.lines.0.description', 'A4 Paper')
        ->assertJsonPath('data.lines.0.qty', 10)
        ->assertJsonPath('data.lines.0.unitPrice', 3.5);

    expect($response->json('data.createdBy'))->toBe($this->admin->name);
});

it('updates a draft by replacing the lines and recomputing totals', function () {
    $id = storeInvoice()->json('data.id');

    $response = $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/invoices/{$id}", invoicePayload([
            'invoice_number' => 'SUP-INV-1001-R1',
            'vat_amount' => 0,
            'discount_amount' => 0,
            'lines' => [
                ['description' => 'Toner', 'qty' => 3, 'unit_price' => 20],
                ['description' => 'Stapler', 'qty' => 2, 'unit_price' => 5],
            ],
        ]))
        ->assertOk()
        ->assertJsonPath('data.invoiceNumber', 'SUP-INV-1001-R1')
        ->assertJsonCount(2, 'data.lines')
        ->assertJsonPath('data.lines.0.description', 'Toner')
        ->assertJsonPath('data.subtotal', 70)
        ->assertJsonPath('data.total', 70)
        ->assertJsonPath('data.grandTotalUsd', 70);

    $invoice = Invoice::query()->with('lines')->findOrFail($id);

    expect($invoice->lines)->toHaveCount(2)
        ->and($invoice->lines->pluck('position')->all())->toBe([0, 1])
        ->and((float) $invoice->total)->toBe(70.0);
});

it('stores the supplier head fields', function () {
    $response = storeInvoice([
        'supplier_phone' => '012345678',
        'supplier_address' => 'No. 12, Street 271, Phnom Penh',
        'transaction_type' => Invoice::TRANSACTION_TYPE_ADVANCE,
        'cash_reference' => 'CSH-001',
        'payment_term' => Invoice::PAYMENT_TERM_CREDIT_1M,
    ])->assertStatus(201);

    $response
        ->assertJsonPath('data.supplierPhone', '012345678')
        ->assertJsonPath('data.supplierAddress', 'No. 12, Street 271, Phnom Penh')
        ->assertJsonPath('data.transactionType', 'advance')
        ->assertJsonPath('data.cashReference', 'CSH-001')
        ->assertJsonPath('data.paymentTerm', 'credit_1m')
        ->assertJsonPath('data.vatPercent', null);

    $invoice = Invoice::query()->findOrFail($response->json('data.id'));

    expect($invoice->supplier_phone)->toBe('012345678')
        ->and($invoice->transaction_type)->toBe('advance')
        ->and($invoice->payment_term)->toBe('credit_1m');
});

it('derives the vat amount from the percent of the discounted subtotal', function () {
    // subtotal 45; discounted base 45 − 5 = 40; 10% of 40 → 4 vat;
    // total = 45 − 5 + 4 = 44.
    $response = storeInvoice([
        'vat_amount' => null,
        'vat_percent' => 10,
        'discount_amount' => 5,
    ])->assertStatus(201);

    $response
        ->assertJsonPath('data.vatPercent', 10)
        ->assertJsonPath('data.subtotal', 45)
        ->assertJsonPath('data.vatAmount', 4)
        ->assertJsonPath('data.discountAmount', 5)
        ->assertJsonPath('data.total', 44);

    $invoice = Invoice::query()->findOrFail($response->json('data.id'));

    expect((float) $invoice->vat_percent)->toBe(10.0)
        ->and((float) $invoice->vat_amount)->toBe(4.0);
});

it('recomputes the vat amount and clears the head fields on update', function () {
    $id = storeInvoice([
        'transaction_type' => Invoice::TRANSACTION_TYPE_ADVANCE,
        'payment_term' => Invoice::PAYMENT_TERM_CREDIT_1M,
        'cash_reference' => 'CSH-001',
        'vat_percent' => 10,
    ])->json('data.id');

    $response = $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/invoices/{$id}", invoicePayload([
            'transaction_type' => null,
            'payment_term' => null,
            'cash_reference' => null,
            'vat_percent' => 5,
        ]))
        ->assertOk()
        ->assertJsonPath('data.transactionType', null)
        ->assertJsonPath('data.paymentTerm', null)
        ->assertJsonPath('data.cashReference', null)
        ->assertJsonPath('data.vatPercent', 5)
        ->assertJsonPath('data.vatAmount', 2);

    $invoice = Invoice::query()->findOrFail($id);

    expect($invoice->transaction_type)->toBeNull()
        ->and($invoice->payment_term)->toBeNull()
        ->and((float) $invoice->vat_percent)->toBe(5.0)
        // subtotal 45 − 5 discount = 40; 5% of 40 → 2.
        ->and((float) $invoice->vat_amount)->toBe(2.0);
});

it('rejects an unknown transaction type or payment term', function () {
    storeInvoice(['transaction_type' => 'wire'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['transaction_type']);

    storeInvoice(['payment_term' => 'net_60'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['payment_term']);
});

it('rejects a vat percent above 100', function () {
    storeInvoice(['vat_percent' => 120])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['vat_percent']);
});

it('stores the per-line detail fields', function () {
    $response = storeInvoice([
        'lines' => [
            [
                'description' => 'A4 Paper',
                'uom' => 'box',
                'campus' => 'CCV',
                'division' => 'ADM',
                'department' => 'FIN',
                'location' => 'Warehouse A',
                'purpose' => 'Office supplies',
                'qty' => 10,
                'unit_price' => 3.5,
            ],
            ['description' => 'Stapler', 'qty' => 2, 'unit_price' => 5],
        ],
    ])->assertStatus(201);

    $response
        ->assertJsonPath('data.lines.0.uom', 'box')
        ->assertJsonPath('data.lines.0.campus', 'CCV')
        ->assertJsonPath('data.lines.0.division', 'ADM')
        ->assertJsonPath('data.lines.0.department', 'FIN')
        ->assertJsonPath('data.lines.0.location', 'Warehouse A')
        ->assertJsonPath('data.lines.0.purpose', 'Office supplies')
        ->assertJsonPath('data.lines.1.uom', null)
        ->assertJsonPath('data.lines.1.campus', null);

    $line = Invoice::query()->findOrFail($response->json('data.id'))->lines()->first();

    expect($line->uom)->toBe('box')
        ->and($line->campus)->toBe('CCV')
        ->and($line->purpose)->toBe('Office supplies');
});

it('rejects a per-line detail field over its max length', function () {
    storeInvoice([
        'lines' => [
            [
                'description' => 'A4 Paper',
                'campus' => str_repeat('C', 65),
                'qty' => 10,
                'unit_price' => 3.5,
            ],
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['lines.0.campus']);
});

it('cannot be modified once it is no longer a draft', function (string $status) {
    $id = storeInvoice()->json('data.id');
    Invoice::query()->whereKey($id)->update(['status' => $status]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/invoices/{$id}", invoicePayload())
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);

    expect($response->json('errors.status.0'))
        ->toBe("An invoice with status '{$status}' cannot be modified.")
        ->and(Invoice::query()->find($id)->status)->toBe($status);
})->with(['recorded', 'cancelled']);

it('records a draft invoice', function () {
    $id = storeInvoice()->json('data.id');

    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/invoices/{$id}/status", ['status' => 'recorded'])
        ->assertOk()
        ->assertJsonPath('data.status', 'recorded');

    expect(Invoice::query()->find($id)->status)->toBe('recorded');
});

it('cancels a recorded invoice', function () {
    $id = storeInvoice()->json('data.id');

    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/invoices/{$id}/status", ['status' => 'recorded'])
        ->assertOk();

    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/invoices/{$id}/status", ['status' => 'cancelled'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect(Invoice::query()->find($id)->status)->toBe('cancelled');
});

it('blocks an invalid status transition', function (string $from) {
    $id = storeInvoice()->json('data.id');
    Invoice::query()->whereKey($id)->update(['status' => $from]);

    $response = $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/invoices/{$id}/status", ['status' => 'recorded'])
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);

    expect($response->json('errors.status.0'))
        ->toBe("An invoice with status '{$from}' cannot move to 'recorded'.")
        ->and(Invoice::query()->find($id)->status)->toBe($from);
})->with(['recorded', 'cancelled']);

it('rejects an unknown target status with a 422 envelope', function () {
    $id = storeInvoice()->json('data.id');

    $response = $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/invoices/{$id}/status", ['status' => 'draft'])
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors']);

    expect($response->json('errors'))->toHaveKey('status');
});

it('forbids staff from changing status', function () {
    $id = storeInvoice()->json('data.id');

    $this->actingAs($this->staff, 'sanctum')
        ->patchJson("/api/v1/invoices/{$id}/status", ['status' => 'recorded'])
        ->assertStatus(403);
});

it('soft-deletes an invoice', function () {
    $id = storeInvoice()->json('data.id');

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/invoices/{$id}")
        ->assertOk()
        ->assertJsonPath('data.deleted', true);

    expect($invoice = Invoice::withTrashed()->find($id))->not->toBeNull()
        ->and($invoice->deleted_at)->not->toBeNull();

    $this->actingAs($this->staff, 'sanctum')
        ->getJson("/api/v1/invoices/{$id}")
        ->assertNotFound();
});

it('blocks deleting an invoice that is no longer a draft', function (string $status) {
    $id = storeInvoice()->json('data.id');
    Invoice::query()->find($id)->update(['status' => $status]);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/invoices/{$id}")
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors'])
        ->assertJsonPath('errors.status.0', "Only a draft invoice can be deleted, this one is '{$status}'.");

    expect(Invoice::query()->find($id))->not->toBeNull()
        ->and(Invoice::onlyTrashed()->find($id))->toBeNull();
})->with(['recorded', 'cancelled']);

it('forbids staff from deleting invoices', function () {
    $id = storeInvoice()->json('data.id');

    $this->actingAs($this->staff, 'sanctum')
        ->deleteJson("/api/v1/invoices/{$id}")
        ->assertStatus(403);
});

it('forbids the owner without the manage permission from editing or deleting their draft', function () {
    $id = storeInvoice()->json('data.id');
    // Ownership alone is not enough: manage AND owner are both required.
    Invoice::query()->find($id)->update(['created_by' => $this->staff->getKey()]);

    $this->actingAs($this->staff, 'sanctum')
        ->patchJson("/api/v1/invoices/{$id}", invoicePayload())
        ->assertStatus(403);

    $this->actingAs($this->staff, 'sanctum')
        ->deleteJson("/api/v1/invoices/{$id}")
        ->assertStatus(403);

    expect(Invoice::query()->find($id))->not->toBeNull()
        ->and(Invoice::onlyTrashed()->find($id))->toBeNull();
});

it('forbids a manage holder from editing or deleting a draft owned by someone else', function () {
    $id = storeInvoice()->json('data.id');
    Invoice::query()->find($id)->update(['created_by' => $this->staff->getKey()]);

    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/invoices/{$id}", invoicePayload())
        ->assertStatus(403);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/v1/invoices/{$id}")
        ->assertStatus(403);

    expect(Invoice::query()->find($id))->not->toBeNull()
        ->and(Invoice::onlyTrashed()->find($id))->toBeNull();
});

it('never returns other entities invoices', function () {
    $foreignAdmin = User::factory()->create(['entity_id' => $this->otherEntity->getKey()]);
    $foreignAdmin->assignRole('admin');

    $foreign = storeInvoice([], $foreignAdmin);
    $foreignId = $foreign->json('data.id');
    $foreignCode = $foreign->json('data.code');

    $codes = $this->actingAs($this->staff, 'sanctum')
        ->getJson('/api/v1/invoices')
        ->assertOk()
        ->json('data.*.code');

    expect($codes)->not->toContain($foreignCode);

    $this->actingAs($this->staff, 'sanctum')
        ->getJson("/api/v1/invoices/{$foreignId}")
        ->assertNotFound();
});

it('builds invoices from the factory with a matching code', function () {
    $invoice = Invoice::factory()->create([
        'entity_id' => $this->entity->getKey(),
        'created_by' => $this->admin->getKey(),
    ]);
    $invoice->lines()->createMany([
        [
            'entity_id' => $this->entity->getKey(),
            'description' => 'A4 Paper',
            'qty' => 10,
            'unit_price' => 3.5,
            'line_total' => 35,
            'position' => 0,
        ],
    ]);

    expect($invoice->code)->toMatch('/^INV-\d{2}-\d{3}$/')
        ->and($invoice->status)->toBe('draft')
        ->and($invoice->lines)->toHaveCount(1);
});
