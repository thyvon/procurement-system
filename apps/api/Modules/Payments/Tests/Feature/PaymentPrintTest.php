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

it('prints a cash request sheet with its lines and no signature block without a flow', function () {
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

        // No approval flow is configured for cash requests here, so the
        // approvals module owns an empty signature block — nothing hardcoded.
        return str_contains($html, 'Cash Request')
            && str_contains($html, 'Cash · សាច់ប្រាក់')
            && str_contains($html, $code)
            && str_contains($html, 'A4 Paper')
            && str_contains($html, '$'."\u{00A0}".'125.50')
            && ! str_contains($html, 'Prepared by');
    });
});

it('prints the approval round signature columns with the stacked Khmer title', function () {
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
                    'labelKh' => 'រៀបចំដោយ',
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

        // The block prints the stacked bilingual title, the signer's name
        // value without its label, and a single English Date line per column.
        return str_contains($html, 'Prepared By')
            && str_contains($html, 'រៀបចំដោយ')
            && str_contains($html, 'Kuy Leng')
            && str_contains($html, 'shrink-0">Date:</span>')
            && ! str_contains($html, 'ឈ្មោះ');
    });
});

it('prints one column per co-signer with each own decision', function () {
    $request = printableCashRequest();
    $coSigner = User::factory()->create(['entity_id' => $this->entity->getKey()]);
    $png = 'data:image/png;base64,'.base64_encode("\x89PNG\r\n\x1a\n");

    $round = ApprovalRequest::factory()->create([
        'entity_id' => $this->entity->getKey(),
        'subject_type' => 'cash_request',
        'subject_id' => $request->getKey(),
        'snapshot' => [
            'steps' => [
                [
                    'position' => 1,
                    'key' => 'reviewed',
                    'label' => 'Reviewed By',
                    'labelKh' => 'ពិនិត្យឡើងវិញ',
                    'actionMode' => 'decide',
                    'showOnPrint' => true,
                    'assigneeId' => $this->admin->getKey(),
                    'assignees' => [
                        ['id' => $this->admin->getKey(), 'name' => $this->admin->name, 'position' => 'Manager', 'signature' => $png],
                        ['id' => $coSigner->getKey(), 'name' => $coSigner->name, 'position' => 'Director', 'signature' => null],
                    ],
                    'assigneeName' => $this->admin->name,
                    'assigneePosition' => 'Manager',
                    'assigneeSignature' => $png,
                ],
            ],
        ],
    ]);

    // Only the first co-signer has approved so far — the pending one keeps
    // an empty date and no signature, but still prints as its own column.
    $round->actions()->create([
        'entity_id' => $this->entity->getKey(),
        'step_position' => 1,
        'step_key' => 'reviewed',
        'action' => 'approve',
        'acted_by' => $this->admin->getKey(),
        'acted_at' => '2026-10-20 09:00:00',
    ]);

    Pdf::fake();

    $this->actingAs($this->admin, 'sanctum')
        ->get("/api/v1/payments/cash-requests/{$request->getKey()}/print")
        ->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) use ($coSigner) {
        $html = $pdf->getHtml();

        // One column per co-signer: the title repeats twice, each column
        // carries that signer's name value and its own Date line — the one
        // who approved prints the image, the one still open the placeholder,
        // and both keep the same reserved slot.
        return substr_count($html, 'Reviewed By') === 2
            && str_contains($html, 'ពិនិត្យឡើងវិញ')
            && str_contains($html, $coSigner->name)
            && substr_count($html, 'shrink-0">Date:</span>') === 2
            && substr_count($html, 'Oct 20, 2026') === 1
            && substr_count($html, 'data:image/png;base64,') === 1
            && substr_count($html, 'class="print-signature__pending"') === 1
            && substr_count($html, 'flex h-14 items-end justify-center') === 2;
    });
});

it('stamps a pending placeholder in the slot of a step that has not decided yet', function () {
    $request = printableCashRequest();
    $png = 'data:image/png;base64,'.base64_encode("\x89PNG\r\n\x1a\n");

    ApprovalRequest::factory()->create([
        'entity_id' => $this->entity->getKey(),
        'subject_type' => 'cash_request',
        'subject_id' => $request->getKey(),
        'snapshot' => [
            'steps' => [
                [
                    'position' => 1,
                    'key' => 'checked',
                    'label' => 'Checked By',
                    'labelKh' => 'ត្រួតពិនិត្យ',
                    'actionMode' => 'decide',
                    'showOnPrint' => true,
                    'assigneeId' => $this->admin->getKey(),
                    'assigneeName' => $this->admin->name,
                    'assigneePosition' => 'Manager',
                    'assigneeSignature' => $png,
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

        // The signature was frozen at submit, but the round is still open and
        // the step carries no act: the slot prints the bilingual placeholder
        // instead of the image, and the slot itself stays reserved so the
        // column keeps its baseline next to signed ones.
        return substr_count($html, 'class="print-signature__pending"') === 1
            && str_contains($html, '>រង់ចាំ</span>')
            && str_contains($html, '>PENDING</span>')
            && ! str_contains($html, 'data:image/png;base64,')
            && substr_count($html, 'flex h-14 items-end justify-center') === 1;
    });
});

it('keeps unsigned slots blank once the round is closed instead of stamping pending', function () {
    $request = printableCashRequest();
    $png = 'data:image/png;base64,'.base64_encode("\x89PNG\r\n\x1a\n");
    $coSigner = User::factory()->create(['entity_id' => $this->entity->getKey()]);

    $round = ApprovalRequest::factory()->create([
        'entity_id' => $this->entity->getKey(),
        'subject_type' => 'cash_request',
        'subject_id' => $request->getKey(),
        'status' => ApprovalRequest::STATUS_CANCELLED,
        'snapshot' => [
            'steps' => [
                [
                    'position' => 1,
                    'key' => 'checked',
                    'label' => 'Checked By',
                    'labelKh' => 'ត្រួតពិនិត្យ',
                    'actionMode' => 'decide',
                    'showOnPrint' => true,
                    'assigneeId' => $this->admin->getKey(),
                    'assigneeName' => $this->admin->name,
                    'assigneePosition' => 'Manager',
                    'assigneeSignature' => $png,
                ],
                [
                    'position' => 2,
                    'key' => 'approved',
                    'label' => 'Approved By',
                    'labelKh' => 'យល់ព្រម',
                    'actionMode' => 'decide',
                    'showOnPrint' => true,
                    'assigneeId' => $coSigner->getKey(),
                    'assigneeName' => $coSigner->name,
                    'assigneePosition' => 'Director',
                    'assigneeSignature' => $png,
                ],
            ],
        ],
    ]);

    // The withdrawal lands on the step the round was parked on; the later
    // step never carried an act of its own.
    $round->actions()->create([
        'entity_id' => $this->entity->getKey(),
        'step_position' => 1,
        'step_key' => 'checked',
        'action' => 'cancel',
        'acted_by' => $this->admin->getKey(),
        'acted_at' => '2026-10-20 09:00:00',
    ]);

    Pdf::fake();

    $this->actingAs($this->admin, 'sanctum')
        ->get("/api/v1/payments/cash-requests/{$request->getKey()}/print")
        ->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) {
        $html = $pdf->getHtml();

        // Nothing waits on a withdrawn round: neither the cancelled step nor
        // the one behind it is stamped PENDING, the frozen signature is not
        // printed as a decision, and both slots stay reserved for alignment.
        return ! str_contains($html, 'class="print-signature__pending"')
            && ! str_contains($html, 'data:image/png;base64,')
            && substr_count($html, 'flex h-14 items-end justify-center') === 2;
    });
});

it('wraps signature columns into centered rows of at most four', function () {
    $request = printableCashRequest();
    $signers = User::factory()->count(4)->create(['entity_id' => $this->entity->getKey()]);

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
                    'labelKh' => 'រៀបចំដោយ',
                    'actionMode' => 'record',
                    'showOnPrint' => true,
                    'assigneeName' => 'Kuy Leng',
                    'assigneePosition' => 'Accountant',
                ],
                [
                    'position' => 2,
                    'key' => 'reviewed',
                    'label' => 'Reviewed By',
                    'labelKh' => 'ពិនិត្យឡើងវិញ',
                    'actionMode' => 'decide',
                    'showOnPrint' => true,
                    'assigneeId' => $signers[0]->getKey(),
                    'assignees' => $signers->map(fn (User $signer) => [
                        'id' => $signer->getKey(),
                        'name' => $signer->name,
                        'position' => 'Officer',
                        'signature' => null,
                    ])->all(),
                    'assigneeName' => $signers[0]->name,
                    'assigneePosition' => 'Officer',
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

        // Five print columns (record + four co-signers) chunk into one full
        // row of four plus a centered row of one, each with its Date line.
        return substr_count($html, 'class="flex justify-center gap-8"') === 2
            && str_contains($html, 'flex: 0 1 calc((100% - 3 * 2rem) / 4)')
            && substr_count($html, 'shrink-0">Date:</span>') === 5
            && str_contains($html, 'Kuy Leng');
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

it('prints the petty cash branch sheet with its own view and no payment block', function () {
    $request = CashRequest::factory()->create([
        'entity_id' => $this->entity->getKey(),
        'created_by' => $this->admin->getKey(),
        'total_usd' => 125.5,
    ]);

    Pdf::fake();

    $this->actingAs($this->admin, 'sanctum')
        ->get("/api/v1/payments/petty-cash-requests/{$request->getKey()}/print")
        ->assertOk();

    $code = $request->code;
    Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $pdf->viewName === 'payments::print.petty-cash-request');
    Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $pdf->downloadName === $code.'-petty-cash-request.pdf');
    Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $pdf->format === 'a4'
        && $pdf->orientation === 'Portrait'
        && $pdf->margins == ['top' => 12, 'right' => 12, 'bottom' => 12, 'left' => 12, 'unit' => 'mm']);
    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) {
        $html = $pdf->getHtml();

        return str_contains($html, 'Petty Cash Request Form')
            && str_contains($html, 'សរុបជាដុល្លារ')
            && str_contains($html, 'TOTAL IN USD')
            && ! str_contains($html, 'Mode of payment')
            && ! str_contains($html, 'Adv. request for')
            // No petty cash flow configured → empty signature block.
            && ! str_contains($html, 'Received by');
    });
});

it('prints the advance branch sheet with the payment and purpose fields', function () {
    $request = CashRequest::factory()->advance()->create([
        'entity_id' => $this->entity->getKey(),
        'created_by' => $this->admin->getKey(),
        'mode_of_payment' => 'Bank transfer',
        'advance_request_for' => 'Office supplies',
    ]);

    Pdf::fake();

    $this->actingAs($this->admin, 'sanctum')
        ->get("/api/v1/payments/advance-requests/{$request->getKey()}/print")
        ->assertOk();

    $code = $request->code;
    Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $pdf->viewName === 'payments::print.advance-request');
    Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $pdf->downloadName === $code.'-advance-request.pdf');
    Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $pdf->format === 'a4'
        && $pdf->orientation === 'Portrait'
        && $pdf->margins == ['top' => 12, 'right' => 12, 'bottom' => 12, 'left' => 12, 'unit' => 'mm']);
    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) {
        $html = $pdf->getHtml();

        return str_contains($html, 'Advance Request Form')
            && str_contains($html, 'Mode of Payment')
            && str_contains($html, 'Bank Transfer')
            && str_contains($html, 'Office supplies');
    });
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
        'vendor_phone' => '012 345 678',
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

        return str_contains($html, 'Petty Cash Clearance')
            && str_contains($html, $code)
            && str_contains($html, 'A4 Paper')
            && str_contains($html, '$'."\u{00A0}".'150.00')
            // The paper form's Vendor Phone No. column prints the stored phone.
            && str_contains($html, 'Vendor phone no.')
            && str_contains($html, '012 345 678')
            // Header meta is Ref / Date / Ref (Request) / Exchange rate only.
            && str_contains($html, 'Ref (Request)')
            && ! str_contains($html, 'Cash request')
            // The three money rows moved from the meta strip into the table's
            // own footer: each label spans every column up to Amount, so the
            // values sit in the Amount column of the same grid.
            && str_contains($html, 'Request amount')
            && str_contains($html, 'Balance')
            && substr_count($html, 'colspan="15"') === 3
            // Line headers and footer labels are English only, as on the paper
            // form — the Khmer stays in the title, meta block and step titles.
            && ! str_contains($html, 'ថ្ងៃវិក្កយបត្រ')
            && ! str_contains($html, 'សរុប')
            // No clearance flow configured → empty signature block.
            && ! str_contains($html, 'Prepared by');
    });
});

it('prints an advance liquidation sheet with its bilingual labels', function () {
    $liquidation = AdvanceLiquidation::factory()->create([
        'entity_id' => $this->entity->getKey(),
        'created_by' => $this->admin->getKey(),
        'total_actual_expense_usd' => 80,
        'remark' => 'Receipts attached.',
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
    Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $pdf->format === 'a4'
        && $pdf->orientation === 'Portrait'
        && $pdf->margins == ['top' => 12, 'right' => 12, 'bottom' => 12, 'left' => 12, 'unit' => 'mm']);
    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) use ($code) {
        $html = $pdf->getHtml();

        return str_contains($html, 'Liquidation of Advance')
            && str_contains($html, $code)
            && str_contains($html, 'Taxi fare')
            && str_contains($html, '80.00')
            && str_contains($html, 'Total Actual Expense')
            && str_contains($html, 'Total Cash Advance')
            && str_contains($html, 'Remaining Cash')
            && str_contains($html, 'ចំណាំ / Remark:')
            && str_contains($html, 'Receipts attached.');
    });
});

it('watermarks every status that is not finally approved', function () {
    $request = printableCashRequest(['status' => CashRequest::STATUS_REJECTED]);

    Pdf::fake();

    $this->actingAs($this->admin, 'sanctum')
        ->get("/api/v1/payments/cash-requests/{$request->getKey()}/print")
        ->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) {
        $html = $pdf->getHtml();

        return str_contains($html, 'print-watermark')
            && str_contains($html, 'REJECTED')
            && str_contains($html, 'NOT OFFICIAL');
    });
});

it('omits the watermark once the document is finally approved or cleared', function () {
    $approved = printableCashRequest(['status' => CashRequest::STATUS_APPROVED]);
    $cleared = printableCashRequest(['status' => CashRequest::STATUS_CLEARED]);

    Pdf::fake();

    $this->actingAs($this->admin, 'sanctum')
        ->get("/api/v1/payments/cash-requests/{$approved->getKey()}/print")
        ->assertOk();

    Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => ! str_contains($pdf->getHtml(), 'class="print-watermark"'));

    Pdf::fake();

    $this->actingAs($this->admin, 'sanctum')
        ->get("/api/v1/payments/cash-requests/{$cleared->getKey()}/print")
        ->assertOk();

    Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => ! str_contains($pdf->getHtml(), 'class="print-watermark"'));
});
