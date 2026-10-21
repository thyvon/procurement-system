<?php

use App\Models\User;
use Modules\Approvals\Models\ApprovalRequest;
use Modules\Organization\Models\Entity;
use Modules\Payments\Models\AdvanceLiquidation;
use Modules\Payments\Models\AdvanceLiquidationLine;
use Modules\Payments\Models\CashClearance;
use Modules\Payments\Models\CashClearanceLine;
use Modules\Payments\Models\CashRequest;
use Modules\Payments\Models\CashRequestLine;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'staff'] as $role) {
        Role::findOrCreate($role, 'sanctum');
    }

    $this->entity = Entity::create(['code' => 'ENT-PRN', 'name' => 'Entity PRN', 'timezone' => 'UTC', 'locale' => 'en']);
    $this->otherEntity = Entity::create(['code' => 'ENT-PRN2', 'name' => 'Entity PRN2', 'timezone' => 'UTC', 'locale' => 'en']);

    $this->admin = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->admin->assignRole('admin');

    $this->staff = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $this->staff->assignRole('staff');
});

function printableCashRequest(array $overrides = []): CashRequest
{
    return CashRequest::factory()->cash()->create(array_merge([
        'entity_id' => test()->entity->getKey(),
        'created_by' => test()->admin->getKey(),
        'total_usd' => 125.5,
    ], $overrides));
}

it('requires authentication for the print sheets', function () {
    $request = printableCashRequest();
    $clearance = CashClearance::factory()->create([
        'entity_id' => $this->entity->getKey(),
        'created_by' => $this->admin->getKey(),
    ]);
    $liquidation = AdvanceLiquidation::factory()->create([
        'entity_id' => $this->entity->getKey(),
        'created_by' => $this->admin->getKey(),
    ]);

    $this->getJson("/api/v1/payments/cash-requests/{$request->getKey()}/print")->assertStatus(401);
    $this->getJson("/api/v1/payments/cash-clearances/{$clearance->getKey()}/print")->assertStatus(401);
    $this->getJson("/api/v1/payments/advance-liquidations/{$liquidation->getKey()}/print")->assertStatus(401);
});

it('prints a cash request sheet with its lines and fallback signature columns', function () {
    $request = printableCashRequest();
    CashRequestLine::factory()->create([
        'entity_id' => $this->entity->getKey(),
        'cash_request_id' => $request->getKey(),
        'position' => 0,
        'description' => 'A4 Paper',
        'currency' => 'USD',
        'amount' => 125.5,
        'amount_usd' => 125.5,
    ]);

    Pdf::fake();

    $this->actingAs($this->admin, 'sanctum')
        ->get("/api/v1/payments/cash-requests/{$request->getKey()}/print")
        ->assertOk();

    $code = $request->code;
    Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $pdf->viewName === 'payments::print.cash-request');
    Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $pdf->downloadName === $code.'-cash-request.pdf');
    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) use ($code) {
        $html = $pdf->getHtml();

        return str_contains($html, 'Cash Request')
            && str_contains($html, $code)
            && str_contains($html, 'A4 Paper')
            && str_contains($html, '$'."\u{00A0}".'125.50')
            && str_contains($html, 'Prepared by · រៀបចំដោយ');
    });
});

it('prints the approval round signature columns when a round exists', function () {
    $request = printableCashRequest();

    ApprovalRequest::factory()->create([
        'entity_id' => $this->entity->getKey(),
        'subject_type' => 'cash_request',
        'subject_id' => $request->getKey(),
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
        ->get("/api/v1/payments/cash-requests/{$request->getKey()}/print")
        ->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) {
        $html = $pdf->getHtml();

        return str_contains($html, 'Prepared By')
            && str_contains($html, 'Kuy Leng')
            && ! str_contains($html, 'Prepared by · រៀបចំដោយ');
    });
});

it('lets staff print because printing follows the view permission', function () {
    $request = printableCashRequest();

    Pdf::fake();

    $this->actingAs($this->staff, 'sanctum')
        ->get("/api/v1/payments/cash-requests/{$request->getKey()}/print")
        ->assertOk();

    Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $pdf->viewName === 'payments::print.cash-request');
});

it('forbids a user without the read permission from printing', function () {
    $request = printableCashRequest();
    $guest = User::factory()->create(['entity_id' => $this->entity->getKey()]);

    Pdf::fake();

    $this->actingAs($guest, 'sanctum')
        ->get("/api/v1/payments/cash-requests/{$request->getKey()}/print")
        ->assertStatus(403);
});

it('returns 404 for a document of another entity', function () {
    $foreignAdmin = User::factory()->create(['entity_id' => $this->otherEntity->getKey()]);
    $foreignAdmin->assignRole('admin');

    $foreign = CashRequest::factory()->create([
        'entity_id' => $this->otherEntity->getKey(),
        'created_by' => $foreignAdmin->getKey(),
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->get("/api/v1/payments/cash-requests/{$foreign->getKey()}/print")
        ->assertNotFound();
});

it('returns 404 on a branch route the branch does not own', function () {
    $cash = printableCashRequest();

    $this->actingAs($this->admin, 'sanctum')
        ->get("/api/v1/payments/petty-cash-requests/{$cash->getKey()}/print")
        ->assertNotFound();
});

it('prints a cash clearance sheet with its bilingual labels', function () {
    $clearance = CashClearance::factory()->create([
        'entity_id' => $this->entity->getKey(),
        'created_by' => $this->admin->getKey(),
        'total_usd' => 150,
    ]);
    CashClearanceLine::factory()->create([
        'entity_id' => $this->entity->getKey(),
        'cash_clearance_id' => $clearance->getKey(),
        'position' => 0,
        'description' => 'A4 Paper',
        'qty' => 10,
        'unit_price_usd' => 15,
        'amount_usd' => 150,
    ]);

    Pdf::fake();

    $this->actingAs($this->admin, 'sanctum')
        ->get("/api/v1/payments/cash-clearances/{$clearance->getKey()}/print")
        ->assertOk();

    $code = $clearance->code;
    Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $pdf->viewName === 'payments::print.cash-clearance');
    Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $pdf->downloadName === $code.'-cash-clearance.pdf');
    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) use ($code) {
        $html = $pdf->getHtml();

        return str_contains($html, 'CASH CLEARANCE')
            && str_contains($html, $code)
            && str_contains($html, 'A4 Paper')
            && str_contains($html, '$'."\u{00A0}".'150.00')
            && str_contains($html, 'អ្នករៀបចំ / Prepared by');
    });
});

it('prints an advance liquidation sheet with its bilingual labels', function () {
    $liquidation = AdvanceLiquidation::factory()->create([
        'entity_id' => $this->entity->getKey(),
        'created_by' => $this->admin->getKey(),
        'total_actual_expense_usd' => 80,
    ]);
    AdvanceLiquidationLine::factory()->create([
        'entity_id' => $this->entity->getKey(),
        'advance_liquidation_id' => $liquidation->getKey(),
        'position' => 0,
        'description' => 'Taxi fare',
        'currency' => 'USD',
        'amount' => 80,
        'amount_usd' => 80,
    ]);

    Pdf::fake();

    $this->actingAs($this->admin, 'sanctum')
        ->get("/api/v1/payments/advance-liquidations/{$liquidation->getKey()}/print")
        ->assertOk();

    $code = $liquidation->code;
    Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $pdf->viewName === 'payments::print.advance-liquidation');
    Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $pdf->downloadName === $code.'-advance-liquidation.pdf');
    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) use ($code) {
        $html = $pdf->getHtml();

        return str_contains($html, 'ADVANCE LIQUIDATION')
            && str_contains($html, $code)
            && str_contains($html, 'Taxi fare')
            && str_contains($html, '$'."\u{00A0}".'80.00')
            && str_contains($html, 'ចំណាយពិតសរុប / Total actual expense:');
    });
});
