<?php

use App\Models\User;
use Illuminate\Testing\TestResponse;
use Modules\Invoices\Models\Invoice;
use Modules\Organization\Models\Entity;
use Modules\Payments\Models\CashRequest;
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

function invoiceLine(array $overrides = []): array
{
    return array_replace_recursive([
        'description' => 'A4 Paper',
        'uom' => 'box',
        'campus' => 'CCV',
        'division' => 'ADM',
        'department' => 'FIN',
        'location' => 'Warehouse A',
        'purpose' => 'Office supplies',
        'qty' => 10,
        'unit_price' => 3.5,
    ], $overrides);
}

function invoicePayload(array $overrides = []): array
{
    return array_replace_recursive([
        'invoice_number' => 'SUP-INV-1001',
        'supplier_name' => 'Acme Trading',
        'supplier_code' => 'SUP-00056',
        'supplier_phone' => '012345678',
        'supplier_address' => 'No. 12, Street 271, Phnom Penh',
        'transaction_type' => Invoice::TRANSACTION_TYPE_CREDIT,
        'payment_term' => Invoice::PAYMENT_TERM_CREDIT_1M,
        'invoice_date' => '2026-10-01',
        'due_date' => '2026-10-31',
        'vat_amount' => 10,
        'discount_amount' => 5,
        'notes' => 'October delivery.',
        'lines' => [
            invoiceLine(),
            invoiceLine(['description' => 'Stapler', 'qty' => 2, 'unit_price' => 5]),
        ],
    ], $overrides);
}

function storeInvoice(array $overrides = [], ?User $user = null): TestResponse
{
    $user ??= test()->admin;

    return test()->actingAs($user, 'sanctum')
        ->postJson('/api/v1/invoices', invoicePayload($overrides));
}

function linkedCashRequest(string $type, string $code, array $overrides = []): CashRequest
{
    return CashRequest::factory()->create(array_merge([
        'entity_id' => test()->entity->getKey(),
        'type' => $type,
        'status' => CashRequest::STATUS_APPROVED,
        'code' => $code,
        // Headroom for the happy-path tests; budget tests pass their own.
        'total_usd' => 1000,
    ], $overrides));
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

it('requires the supplier head fields', function (string $field) {
    storeInvoice([$field => null])
        ->assertStatus(422)
        ->assertJsonValidationErrors([$field]);

    expect(Invoice::query()->count())->toBe(0);
})->with(['supplier_phone', 'supplier_address', 'transaction_type', 'payment_term']);

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
    $cashRequest = linkedCashRequest(CashRequest::TYPE_ADVANCE, 'CSH-001');

    $response = storeInvoice([
        'supplier_phone' => '012345678',
        'supplier_address' => 'No. 12, Street 271, Phnom Penh',
        'transaction_type' => Invoice::TRANSACTION_TYPE_ADVANCE,
        'cash_request_id' => $cashRequest->getKey(),
        'payment_term' => Invoice::PAYMENT_TERM_CREDIT_1M,
    ])->assertStatus(201);

    $response
        ->assertJsonPath('data.supplierPhone', '012345678')
        ->assertJsonPath('data.supplierAddress', 'No. 12, Street 271, Phnom Penh')
        ->assertJsonPath('data.transactionType', 'advance')
        ->assertJsonPath('data.cashRequestId', $cashRequest->getKey())
        // The reference code is derived server-side from the linked request.
        ->assertJsonPath('data.cashReference', 'CSH-001')
        ->assertJsonPath('data.paymentTerm', 'credit_1m')
        ->assertJsonPath('data.vatPercent', null);

    $invoice = Invoice::query()->findOrFail($response->json('data.id'));

    expect($invoice->supplier_phone)->toBe('012345678')
        ->and($invoice->transaction_type)->toBe('advance')
        ->and($invoice->cash_request_id)->toBe($cashRequest->getKey())
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

it('recomputes the vat amount and replaces the head fields on update', function () {
    $cashRequest = linkedCashRequest(CashRequest::TYPE_ADVANCE, 'CSH-001');

    $id = storeInvoice([
        'transaction_type' => Invoice::TRANSACTION_TYPE_ADVANCE,
        'payment_term' => Invoice::PAYMENT_TERM_CREDIT_1M,
        'cash_request_id' => $cashRequest->getKey(),
        'vat_percent' => 10,
    ])->json('data.id');

    // Transaction type, payment term and the supplier fields are required,
    // so an update must swap them for new values; the cash link is only
    // clearable by moving the invoice to credit (where it is not required).
    $response = $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/invoices/{$id}", invoicePayload([
            'transaction_type' => Invoice::TRANSACTION_TYPE_CREDIT,
            'payment_term' => Invoice::PAYMENT_TERM_CREDIT_1W,
            'cash_request_id' => null,
            'vat_percent' => 5,
        ]))
        ->assertOk()
        ->assertJsonPath('data.transactionType', 'credit')
        ->assertJsonPath('data.paymentTerm', 'credit_1w')
        ->assertJsonPath('data.cashReference', null)
        ->assertJsonPath('data.cashRequestId', null)
        ->assertJsonPath('data.vatPercent', 5)
        ->assertJsonPath('data.vatAmount', 2);

    $invoice = Invoice::query()->findOrFail($id);

    expect($invoice->transaction_type)->toBe('credit')
        ->and($invoice->payment_term)->toBe('credit_1w')
        ->and($invoice->cash_reference)->toBeNull()
        ->and($invoice->cash_request_id)->toBeNull()
        ->and((float) $invoice->vat_percent)->toBe(5.0)
        // subtotal 45 − 5 discount = 40; 5% of 40 → 2.
        ->and((float) $invoice->vat_amount)->toBe(2.0);
});

it('requires a cash request for cash-settled invoices but not for credit', function () {
    storeInvoice([
        'transaction_type' => Invoice::TRANSACTION_TYPE_PETTY_CASH,
        'cash_request_id' => null,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['cash_request_id']);

    storeInvoice([
        'transaction_type' => Invoice::TRANSACTION_TYPE_ADVANCE,
        'cash_request_id' => null,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['cash_request_id']);

    storeInvoice([
        'transaction_type' => Invoice::TRANSACTION_TYPE_CREDIT,
        'cash_request_id' => null,
    ])->assertStatus(201);
});

it('rejects an unknown or mismatched cash request', function () {
    storeInvoice([
        'transaction_type' => Invoice::TRANSACTION_TYPE_PETTY_CASH,
        'cash_request_id' => '01K00000000000000000000000',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['cash_request_id']);

    // A petty cash invoice may only draw on a petty cash request.
    $advanceRequest = linkedCashRequest(CashRequest::TYPE_ADVANCE, 'ADV-001');

    storeInvoice([
        'transaction_type' => Invoice::TRANSACTION_TYPE_PETTY_CASH,
        'cash_request_id' => $advanceRequest->getKey(),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['cash_request_id']);
});

it('blocks a cash-settled invoice that would exceed its cash request budget', function () {
    // The default payload totals 50 (45 − 5 + 10), the request holds 40.
    $cashRequest = linkedCashRequest(CashRequest::TYPE_PETTY_CASH, 'PC-100', ['total_usd' => 40]);

    $response = storeInvoice([
        'transaction_type' => Invoice::TRANSACTION_TYPE_PETTY_CASH,
        'cash_request_id' => $cashRequest->getKey(),
    ])
        ->assertStatus(422)
        ->assertJsonPath('statusCode', 422)
        ->assertJsonStructure(['statusCode', 'message', 'error', 'correlationId', 'errors' => ['cash_request_id']]);

    // The message names the request and the amounts involved.
    expect($response->json('errors.cash_request_id.0'))
        ->toContain('PC-100')
        ->toContain('40.00')
        ->toContain('50.00')
        ->and(Invoice::query()->count())->toBe(0);
});

it('allows a cash-settled invoice that exactly consumes the budget', function () {
    $cashRequest = linkedCashRequest(CashRequest::TYPE_PETTY_CASH, 'PC-101', ['total_usd' => 50]);

    storeInvoice([
        'transaction_type' => Invoice::TRANSACTION_TYPE_PETTY_CASH,
        'cash_request_id' => $cashRequest->getKey(),
    ])->assertStatus(201);
});

it('counts earlier non-cancelled invoices against the budget', function () {
    $cashRequest = linkedCashRequest(CashRequest::TYPE_PETTY_CASH, 'PC-102', ['total_usd' => 100]);

    storeInvoice([
        'transaction_type' => Invoice::TRANSACTION_TYPE_PETTY_CASH,
        'cash_request_id' => $cashRequest->getKey(),
    ])->assertStatus(201);

    // 50 used + 50 = 100, the exact remaining amount.
    storeInvoice([
        'transaction_type' => Invoice::TRANSACTION_TYPE_PETTY_CASH,
        'cash_request_id' => $cashRequest->getKey(),
    ])->assertStatus(201);

    // 100 used + 50 would go over.
    storeInvoice([
        'transaction_type' => Invoice::TRANSACTION_TYPE_PETTY_CASH,
        'cash_request_id' => $cashRequest->getKey(),
    ])->assertStatus(422)->assertJsonValidationErrors(['cash_request_id']);

    expect(Invoice::query()->count())->toBe(2);
});

it('frees the budget when a linked invoice is cancelled', function () {
    $cashRequest = linkedCashRequest(CashRequest::TYPE_PETTY_CASH, 'PC-103', ['total_usd' => 50]);

    $id = storeInvoice([
        'transaction_type' => Invoice::TRANSACTION_TYPE_PETTY_CASH,
        'cash_request_id' => $cashRequest->getKey(),
    ])->assertStatus(201)->json('data.id');

    storeInvoice([
        'transaction_type' => Invoice::TRANSACTION_TYPE_PETTY_CASH,
        'cash_request_id' => $cashRequest->getKey(),
    ])->assertStatus(422);

    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/invoices/{$id}/status", ['status' => 'cancelled'])
        ->assertOk();

    storeInvoice([
        'transaction_type' => Invoice::TRANSACTION_TYPE_PETTY_CASH,
        'cash_request_id' => $cashRequest->getKey(),
    ])->assertStatus(201);
});

it('excludes the invoice itself from the budget check on update', function () {
    $cashRequest = linkedCashRequest(CashRequest::TYPE_ADVANCE, 'ADV-100', ['total_usd' => 50]);

    $id = storeInvoice([
        'transaction_type' => Invoice::TRANSACTION_TYPE_ADVANCE,
        'cash_request_id' => $cashRequest->getKey(),
    ])->assertStatus(201)->json('data.id');

    // Re-saving at its own total passes: the invoice is its own budget use.
    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/invoices/{$id}", invoicePayload([
            'transaction_type' => Invoice::TRANSACTION_TYPE_ADVANCE,
            'cash_request_id' => $cashRequest->getKey(),
        ]))
        ->assertOk();

    // Growing past the request amount is refused.
    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/invoices/{$id}", invoicePayload([
            'transaction_type' => Invoice::TRANSACTION_TYPE_ADVANCE,
            'cash_request_id' => $cashRequest->getKey(),
            'discount_amount' => 0,
            'vat_amount' => 0,
            'lines' => [invoiceLine(['qty' => 1, 'unit_price' => 60])],
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['cash_request_id']);

    expect((float) Invoice::query()->find($id)->grand_total_usd)->toBe(50.0);
});

it('compares the budget in USD for foreign-currency invoices', function () {
    $cashRequest = linkedCashRequest(CashRequest::TYPE_ADVANCE, 'ADV-101', ['total_usd' => 100]);

    // 600,000 KHR at 4,000 = 150 USD against 100 USD approved.
    storeInvoice([
        'transaction_type' => Invoice::TRANSACTION_TYPE_ADVANCE,
        'cash_request_id' => $cashRequest->getKey(),
        'currency' => 'KHR',
        'exchange_rate' => 4000,
        'discount_amount' => 0,
        'vat_amount' => 0,
        'lines' => [invoiceLine(['qty' => 1, 'unit_price' => 600000])],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['cash_request_id']);

    // The same invoice at 10,000 rate is 60 USD — within the budget.
    storeInvoice([
        'transaction_type' => Invoice::TRANSACTION_TYPE_ADVANCE,
        'cash_request_id' => $cashRequest->getKey(),
        'currency' => 'KHR',
        'exchange_rate' => 10000,
        'discount_amount' => 0,
        'vat_amount' => 0,
        'lines' => [invoiceLine(['qty' => 1, 'unit_price' => 600000])],
    ])->assertStatus(201);
});

it('derives paid_at from the invoice date for cash-settled invoices', function () {
    $cashRequest = linkedCashRequest(CashRequest::TYPE_PETTY_CASH, 'PC-001');

    $id = storeInvoice([
        'transaction_type' => Invoice::TRANSACTION_TYPE_PETTY_CASH,
        'cash_request_id' => $cashRequest->getKey(),
        'invoice_date' => '2026-10-01',
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.paidAt', '2026-10-01 00:00')
        ->json('data.id');

    expect(Invoice::query()->findOrFail($id)->paid_at?->format('Y-m-d'))->toBe('2026-10-01');
});

it('leaves paid_at empty for credit invoices', function () {
    $id = storeInvoice()->json('data.id');

    $invoice = Invoice::query()->findOrFail($id);

    expect($invoice->transaction_type)->toBe('credit')
        ->and($invoice->paid_at)->toBeNull();
});

it('re-stamps paid_at on update when the transaction type changes', function () {
    $advanceRequest = linkedCashRequest(CashRequest::TYPE_ADVANCE, 'CSH-001');
    $pettyRequest = linkedCashRequest(CashRequest::TYPE_PETTY_CASH, 'PC-002');

    $id = storeInvoice([
        'transaction_type' => Invoice::TRANSACTION_TYPE_ADVANCE,
        'cash_request_id' => $advanceRequest->getKey(),
        'invoice_date' => '2026-10-01',
    ])->json('data.id');

    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/invoices/{$id}", invoicePayload([
            'transaction_type' => Invoice::TRANSACTION_TYPE_PETTY_CASH,
            'cash_request_id' => $pettyRequest->getKey(),
            'invoice_date' => '2026-10-15',
        ]))
        ->assertOk()
        // The link re-stamps too: reference follows the newly picked request.
        ->assertJsonPath('data.cashReference', 'PC-002')
        ->assertJsonPath('data.cashRequestId', $pettyRequest->getKey());

    expect(Invoice::query()->findOrFail($id)->paid_at?->format('Y-m-d'))->toBe('2026-10-15');

    // Moving to credit clears the stamp and the cash link again.
    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/invoices/{$id}", invoicePayload([
            'transaction_type' => Invoice::TRANSACTION_TYPE_CREDIT,
            'cash_request_id' => null,
        ]))
        ->assertOk()
        ->assertJsonPath('data.cashReference', null)
        ->assertJsonPath('data.cashRequestId', null);

    expect(Invoice::query()->findOrFail($id)->paid_at)->toBeNull();
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
            invoiceLine(),
            invoiceLine([
                'description' => 'Stapler',
                'uom' => 'pcs',
                'campus' => 'PP',
                'division' => 'OPS',
                'department' => 'IT',
                'location' => 'Head Office',
                'purpose' => 'Repairs',
                'qty' => 2,
                'unit_price' => 5,
            ]),
        ],
    ])->assertStatus(201);

    $response
        ->assertJsonPath('data.lines.0.uom', 'box')
        ->assertJsonPath('data.lines.0.campus', 'CCV')
        ->assertJsonPath('data.lines.0.division', 'ADM')
        ->assertJsonPath('data.lines.0.department', 'FIN')
        ->assertJsonPath('data.lines.0.location', 'Warehouse A')
        ->assertJsonPath('data.lines.0.purpose', 'Office supplies')
        ->assertJsonPath('data.lines.1.uom', 'pcs')
        ->assertJsonPath('data.lines.1.campus', 'PP')
        ->assertJsonPath('data.lines.1.purpose', 'Repairs');

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

it('requires every line detail field', function (string $field) {
    storeInvoice(['lines' => [[$field => null]]])
        ->assertStatus(422)
        ->assertJsonValidationErrors(["lines.0.$field"]);

    expect(Invoice::query()->count())->toBe(0);
})->with([
    'description',
    'uom',
    'campus',
    'division',
    'department',
    'location',
    'purpose',
    'qty',
    'unit_price',
]);

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
