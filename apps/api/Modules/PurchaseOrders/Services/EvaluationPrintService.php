<?php

namespace Modules\PurchaseOrders\Services;

use App\Models\User;
use App\Support\PrintFormatter;
use App\Support\PrintStylesheet;
use Modules\Approvals\Repositories\ApprovalRequestRepositoryInterface;
use Modules\Approvals\Services\PrintSignatureService;
use Modules\Organization\Services\LogoService;
use Modules\PurchaseOrders\Models\Evaluation;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

/**
 * Context and response for the price evaluation print sheet — the server-side
 * twin of apps/web/src/features/purchase-orders/components/evaluation-print.tsx.
 * The matrix math mirrors the web's fromEvaluation()/lineTotal()/subTotal()/
 * grandTotal() helpers (first selected line wins per item; totals are
 * re-derived from the cells, never read from the stored subtotal columns).
 */
class EvaluationPrintService
{
    public function __construct(
        private readonly ApprovalRequestRepositoryInterface $approvals,
        private readonly PrintSignatureService $signatures,
        private readonly LogoService $logos,
    ) {}

    public function forEvaluation(Evaluation $evaluation, User $user): PdfBuilder
    {
        $evaluation->load(['items', 'quotations.lines']);

        $items = $evaluation->items->map(fn ($item): array => [
            'uid' => (string) $item->getKey(),
            'itemCode' => $item->item_code,
            'description' => $item->description,
            'qty' => (float) $item->qty,
            'uom' => $item->uom,
        ])->values()->all();

        /** @var list<array<string, mixed>> $panels */
        $panels = [];
        $seenWinners = [];

        foreach ($evaluation->quotations as $quotation) {
            $pricing = [];

            foreach ($items as $item) {
                $line = $quotation->lines->firstWhere('evaluation_item_id', $item['uid']);
                $brand = '';
                $unitCost = null;
                $lineTotal = null;
                $selected = false;

                if ($line !== null) {
                    $brand = (string) ($line->brand ?? '');
                    $unitCost = (float) $line->unit_cost;
                    $lineTotal = $item['qty'] * $unitCost;
                    $selected = (bool) $line->is_selected;
                }

                if ($selected) {
                    if (isset($seenWinners[$item['uid']])) {
                        $selected = false;
                    } else {
                        $seenWinners[$item['uid']] = true;
                    }
                }

                $pricing[$item['uid']] = [
                    'brand' => $brand,
                    'unitCost' => $unitCost,
                    'lineTotal' => $lineTotal,
                    'selected' => $selected,
                ];
            }

            $subTotal = 0.0;
            foreach ($pricing as $cell) {
                $subTotal += $cell['lineTotal'] ?? 0.0;
            }

            $discountDisplay = $quotation->discount != 0 ? PrintFormatter::numeric($quotation->discount) : '';
            $vatDisplay = $quotation->vat != 0 ? PrintFormatter::numeric($quotation->vat) : '';
            $discountAmount = $discountDisplay === '' ? 0.0 : (float) $discountDisplay;
            $vatAmount = $vatDisplay === '' ? 0.0 : (float) $vatDisplay;

            $panels[] = [
                'label' => $this->quoteLabel(count($panels)),
                'supplierName' => $quotation->supplier_name,
                'address' => $quotation->supplier_address,
                'phone' => $quotation->supplier_phone,
                'pricing' => $pricing,
                'discountDisplay' => $discountDisplay,
                'discountAmount' => $discountAmount,
                'vatDisplay' => $vatDisplay,
                'vatAmount' => $vatAmount,
                'subTotal' => $subTotal,
                'grandTotal' => $subTotal - $discountAmount + $vatAmount,
                'criteria' => [
                    'price' => (string) ($quotation->price ?? ''),
                    'quality' => (string) ($quotation->quality ?? ''),
                    'leadTime' => (string) ($quotation->lead_time ?? ''),
                    'warranty' => (string) ($quotation->warranty ?? ''),
                    'paymentTerms' => (string) ($quotation->payment_terms ?? ''),
                    'otherRemarks' => (string) ($quotation->other_remarks ?? ''),
                ],
            ];
        }

        return Pdf::view('purchase-orders::print.evaluation', [
            'css' => PrintStylesheet::load(),
            'logo' => $this->logos->forUser($user),
            'evaluation' => [
                'code' => $evaluation->code,
                'currency' => (string) $evaluation->currency,
                'recommendationBasis' => $evaluation->recommendation_basis,
            ],
            'items' => $items,
            'quotations' => $panels,
            'columns' => $this->signatures->columns(
                $this->approvals->latestForSubject('evaluation', (string) $evaluation->getKey()),
                [
                    ['key' => 'prepared', 'title' => 'Prepared by'],
                    ['key' => 'reviewed', 'title' => 'Reviewed by'],
                    ['key' => 'approved', 'title' => 'Approved by'],
                ],
            ),
        ])
            ->format('a4')
            ->landscape()
            ->margins(8, 8, 8, 8, 'mm')
            ->name($evaluation->code.'-price-evaluation.pdf');
    }

    /**
     * The web's quoteLabel(): roman numerals for the first three quotations,
     * then the 1-based index.
     */
    private function quoteLabel(int $index): string
    {
        return ['I', 'II', 'III'][$index] ?? (string) ($index + 1);
    }
}
