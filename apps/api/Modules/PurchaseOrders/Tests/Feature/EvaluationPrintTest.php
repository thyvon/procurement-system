<?php

use App\Models\User;
use Modules\Approvals\Models\ApprovalRequest;
use Modules\Organization\Models\Entity;
use Modules\PurchaseOrders\Models\Evaluation;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'staff'] as $role) {
        Role::findOrCreate($role, 'sanctum');
    }

    $this->entity = Entity::create(['code' => 'ENT-EPR', 'name' => 'Entity EPR', 'timezone' => 'UTC', 'locale' => 'en']);
    $this->otherEntity = Entity::create(['code' => 'ENT-EPR2', 'name' => 'Entity EPR2', 'timezone' => 'UTC', 'locale' => 'en']);

    $this->admin = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->admin->assignRole('admin');

    $this->staff = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->staff->assignRole('staff');
});

function printableEvaluationPayload(array $overrides = []): array
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

function printableEvaluation(array $overrides = []): Evaluation
{
    $response = test()->actingAs(test()->admin, 'sanctum')
        ->postJson('/api/v1/purchase-orders/evaluations', printableEvaluationPayload($overrides));
    $response->assertStatus(201);

    return Evaluation::query()->findOrFail($response->json('data.id'));
}

it('requires authentication for the print sheet', function () {
    // Built directly so no actingAs() guard lingers for the guest request.
    $evaluation = Evaluation::create([
        'entity_id' => $this->entity->getKey(),
        'code' => 'EVAL-26-001',
        'status' => 'rejected',
        'recommendation_basis' => 'Best value for money.',
        'created_by' => $this->admin->getKey(),
    ]);

    $this->getJson("/api/v1/purchase-orders/evaluations/{$evaluation->getKey()}/print")->assertStatus(401);
});

it('prints the price evaluation matrix with computed totals', function () {
    $evaluation = printableEvaluation();

    Pdf::fake();

    $this->actingAs($this->admin, 'sanctum')
        ->get("/api/v1/purchase-orders/evaluations/{$evaluation->getKey()}/print")
        ->assertOk();

    $code = $evaluation->code;
    Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $pdf->viewName === 'purchase-orders::print.evaluation');
    Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $pdf->downloadName === $code.'-price-evaluation.pdf');
    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) use ($code) {
        $html = $pdf->getHtml();

        return str_contains($html, 'Price Evaluation Form')
            && str_contains($html, 'MJQE0055')
            && str_contains($html, $code)
            && str_contains($html, 'Quotation I')
            && str_contains($html, 'Quotation II')
            && str_contains($html, 'Kuy Leng')
            && str_contains($html, 'Acme Trading')
            && str_contains($html, 'Basis of supplier recommend:')
            && str_contains($html, 'Best value for money.')
            && str_contains($html, '1. Price')
            && str_contains($html, '6. Other Remarks');
    });
});

it('re-derives subtotals and grand totals from the cells', function () {
    $evaluation = printableEvaluation();

    Pdf::fake();

    $this->actingAs($this->admin, 'sanctum')
        ->get("/api/v1/purchase-orders/evaluations/{$evaluation->getKey()}/print")
        ->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) {
        $html = $pdf->getHtml();
        $nbsp = "\u{00A0}";

        // Quotation I: 10x3.5 + 2x5 = 45, minus 5 discount, plus 10 VAT.
        // Quotation II: 10x4 + 2x4.5 = 49, no discount or VAT.
        return str_contains($html, '$'.$nbsp.'45.00')
            && str_contains($html, '$'.$nbsp.'50.00')
            && str_contains($html, '$'.$nbsp.'49.00')
            && str_contains($html, '$'.$nbsp.'5.00')
            && str_contains($html, '$'.$nbsp.'10.00');
    });
});

it('formats a KHR evaluation with the riel symbol and no decimals', function () {
    $evaluation = printableEvaluation([
        'currency' => 'KHR',
        'exchange_rate' => 4100,
    ]);

    Pdf::fake();

    $this->actingAs($this->admin, 'sanctum')
        ->get("/api/v1/purchase-orders/evaluations/{$evaluation->getKey()}/print")
        ->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) {
        $html = $pdf->getHtml();

        return str_contains($html, "\u{17DB}")
            && str_contains($html, "\u{17DB}\u{00A0}45");
    });
});

it('prints the approval round signature columns when a round exists', function () {
    $evaluation = printableEvaluation();

    ApprovalRequest::factory()->create([
        'entity_id' => $this->entity->getKey(),
        'subject_type' => 'evaluation',
        'subject_id' => $evaluation->getKey(),
        'snapshot' => [
            'steps' => [
                [
                    'position' => 1,
                    'key' => 'prepared',
                    'label' => 'Prepared By',
                    'actionMode' => 'record',
                    'showOnPrint' => true,
                    'assigneeId' => null,
                    'assigneeName' => 'Kuy Leng',
                    'assigneePosition' => 'Accountant',
                    'assigneeSignature' => null,
                ],
            ],
        ],
    ]);

    Pdf::fake();

    $this->actingAs($this->admin, 'sanctum')
        ->get("/api/v1/purchase-orders/evaluations/{$evaluation->getKey()}/print")
        ->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) {
        $html = $pdf->getHtml();

        return str_contains($html, 'Prepared By')
            && str_contains($html, 'Kuy Leng')
            && ! str_contains($html, '>Prepared by<');
    });
});

it('lets staff print because printing follows the view permission', function () {
    $evaluation = printableEvaluation();

    Pdf::fake();

    $this->actingAs($this->staff, 'sanctum')
        ->get("/api/v1/purchase-orders/evaluations/{$evaluation->getKey()}/print")
        ->assertOk();

    Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $pdf->viewName === 'purchase-orders::print.evaluation');
});

it('forbids a user without the read permission from printing', function () {
    $evaluation = printableEvaluation();
    $guest = User::factory()->create(['entity_id' => $this->entity->getKey()]);

    Pdf::fake();

    $this->actingAs($guest, 'sanctum')
        ->get("/api/v1/purchase-orders/evaluations/{$evaluation->getKey()}/print")
        ->assertStatus(403);
});

it('returns 404 for an evaluation of another entity', function () {
    $foreignAdmin = User::factory()->create(['entity_id' => $this->otherEntity->getKey()]);
    $foreignAdmin->assignRole('admin');

    $foreignResponse = $this->actingAs($foreignAdmin, 'sanctum')
        ->postJson('/api/v1/purchase-orders/evaluations', printableEvaluationPayload());
    $foreignResponse->assertStatus(201);

    $this->actingAs($this->admin, 'sanctum')
        ->get("/api/v1/purchase-orders/evaluations/{$foreignResponse->json('data.id')}/print")
        ->assertNotFound();
});
