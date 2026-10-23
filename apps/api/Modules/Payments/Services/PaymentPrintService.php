<?php

namespace Modules\Payments\Services;

use App\Models\User;
use App\Support\PrintStylesheet;
use App\Support\PrintWatermark;
use Modules\Approvals\Services\PrintSignatureService;
use Modules\Organization\Services\LogoService;
use Modules\Payments\Models\AdvanceLiquidation;
use Modules\Payments\Models\CashClearance;
use Modules\Payments\Models\CashRequest;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

/**
 * Context and response for the payment print sheets — the official A4
 * documents previewed by the web's shared print dialog (`usePrintPreview`
 * in apps/web). Each method passes the document; the signature block comes
 * whole from the approvals module (`PrintSignatureService::forSubject`) and
 * the sheet itself (labels, conditionals, money) stays in the Blade view.
 */
class PaymentPrintService
{
    public function __construct(
        private readonly PrintSignatureService $signatures,
        private readonly LogoService $logos,
    ) {}

    public function forCashRequest(CashRequest $cashRequest, User $user): PdfBuilder
    {
        $cashRequest->load(['lines', 'creator']);

        [$view, $sheet] = match ($cashRequest->type) {
            CashRequest::TYPE_PETTY_CASH => ['payments::print.petty-cash-request', 'petty-cash-request'],
            CashRequest::TYPE_ADVANCE => ['payments::print.advance-request', 'advance-request'],
            default => ['payments::print.cash-request', 'cash-request'],
        };

        $isPortraitForm = in_array($cashRequest->type, [CashRequest::TYPE_PETTY_CASH, CashRequest::TYPE_ADVANCE], true);
        // The petty cash and advance sheets are official portrait A4 forms;
        // the other branches keep the landscape working sheets.
        $margin = $isPortraitForm ? 12 : 8;

        $pdf = Pdf::view($view, [
            'css' => PrintStylesheet::load(),
            'logo' => $this->logos->forUser($user),
            'watermark' => PrintWatermark::for($cashRequest->status),
            'cashRequest' => [
                'code' => $cashRequest->code,
                'status' => $cashRequest->status,
                'campus' => $cashRequest->campus,
                'division' => $cashRequest->division,
                'department' => $cashRequest->department,
                'requestDate' => $cashRequest->request_date?->format('Y-m-d'),
                'exchangeRate' => (float) $cashRequest->exchange_rate,
                'modeOfPayment' => $cashRequest->mode_of_payment,
                'paymentTo' => $cashRequest->payment_to,
                'bankAccountNo' => $cashRequest->bank_account_no,
                'bankName' => $cashRequest->bank_name,
                'advanceRequestFor' => $cashRequest->advance_request_for,
                'totalUsd' => (float) $cashRequest->total_usd,
                'remark' => $cashRequest->remark,
                'createdBy' => $cashRequest->creator?->name,
                'createdByPosition' => $cashRequest->creator?->position,
                'createdById' => $cashRequest->created_by,
                'lines' => $cashRequest->lines->map(fn ($line): array => [
                    'id' => $line->getKey(),
                    'description' => $line->description,
                    'currency' => $line->currency,
                    'amount' => (float) $line->amount,
                    'amountUsd' => (float) $line->amount_usd,
                ])->all(),
            ],
            'columns' => $this->signatures->forSubject(
                $this->subjectType($cashRequest),
                (string) $cashRequest->getKey(),
            ),
        ])
            ->format('a4')
            ->margins($margin, $margin, $margin, $margin, 'mm');

        return ($isPortraitForm ? $pdf->portrait() : $pdf->landscape())
            ->name($cashRequest->code.'-'.$sheet.'.pdf');
    }

    public function forCashClearance(CashClearance $cashClearance, User $user): PdfBuilder
    {
        $cashClearance->load(['lines', 'creator', 'cashRequest']);

        return Pdf::view('payments::print.cash-clearance', [
            'css' => PrintStylesheet::load(),
            'logo' => $this->logos->forUser($user),
            'watermark' => PrintWatermark::for($cashClearance->status),
            'clearance' => [
                'code' => $cashClearance->code,
                'clearanceDate' => $cashClearance->clearance_date?->format('Y-m-d'),
                'cashRequestCode' => $cashClearance->cashRequest?->code ?? (string) $cashClearance->cash_request_id,
                'exchangeRate' => (float) $cashClearance->exchange_rate,
                'requestAmountUsd' => (float) $cashClearance->request_amount_usd,
                'totalUsd' => (float) $cashClearance->total_usd,
                'balanceUsd' => (float) $cashClearance->balance_usd,
                'lines' => $cashClearance->lines->map(fn ($line): array => [
                    'id' => $line->getKey(),
                    'invoiceDate' => $line->invoice_date?->format('Y-m-d'),
                    'prRef' => $line->pr_ref,
                    'purchaser' => $line->purchaser,
                    'description' => $line->description,
                    'vendor' => $line->vendor,
                    'campus' => $line->campus,
                    'division' => $line->division,
                    'department' => $line->department,
                    'qty' => (float) $line->qty,
                    'uom' => $line->uom,
                    'unitPriceUsd' => (float) $line->unit_price_usd,
                    'discountUsd' => (float) $line->discount_usd,
                    'vatUsd' => (float) $line->vat_usd,
                    'amountUsd' => (float) $line->amount_usd,
                ])->all(),
            ],
            'columns' => $this->signatures->forSubject('cash_clearance', (string) $cashClearance->getKey()),
        ])
            ->format('a4')
            ->landscape()
            ->margins(8, 8, 8, 8, 'mm')
            ->name($cashClearance->code.'-cash-clearance.pdf');
    }

    public function forAdvanceLiquidation(AdvanceLiquidation $liquidation, User $user): PdfBuilder
    {
        $liquidation->load(['lines', 'creator', 'cashRequest']);

        return Pdf::view('payments::print.advance-liquidation', [
            'css' => PrintStylesheet::load(),
            'logo' => $this->logos->forUser($user),
            'watermark' => PrintWatermark::for($liquidation->status),
            'liquidation' => [
                'code' => $liquidation->code,
                'liquidationDate' => $liquidation->liquidation_date?->format('Y-m-d'),
                'cashRequestCode' => $liquidation->cashRequest?->code ?? (string) $liquidation->cash_request_id,
                'exchangeRate' => (float) $liquidation->exchange_rate,
                'createdBy' => $liquidation->creator?->name,
                'createdByPosition' => $liquidation->creator?->position,
                'createdById' => $liquidation->created_by,
                'campus' => $liquidation->campus,
                'division' => $liquidation->division,
                'department' => $liquidation->department,
                'totalCashAdvanceUsd' => (float) $liquidation->total_cash_advance_usd,
                'totalActualExpenseUsd' => (float) $liquidation->total_actual_expense_usd,
                'remainingCashUsd' => (float) $liquidation->remaining_cash_usd,
                'remark' => $liquidation->remark,
                'lines' => $liquidation->lines->map(fn ($line): array => [
                    'id' => $line->getKey(),
                    'description' => $line->description,
                    'currency' => $line->currency,
                    'amount' => (float) $line->amount,
                    'amountUsd' => (float) $line->amount_usd,
                ])->all(),
            ],
            'columns' => $this->signatures->forSubject('advance_liquidation', (string) $liquidation->getKey()),
        ])
            ->format('a4')
            ->portrait()
            ->margins(12, 12, 12, 12, 'mm')
            ->name($liquidation->code.'-advance-liquidation.pdf');
    }

    /**
     * Approval subject type for a cash request branch — mirrors
     * `approvalSubjectType()` in the web's cash-request-helpers.
     */
    private function subjectType(CashRequest $cashRequest): string
    {
        return match ($cashRequest->type) {
            CashRequest::TYPE_PETTY_CASH => 'petty_cash_request',
            CashRequest::TYPE_ADVANCE => 'advance_request',
            default => 'cash_request',
        };
    }
}
