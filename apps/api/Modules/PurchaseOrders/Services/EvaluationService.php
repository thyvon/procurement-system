<?php

namespace Modules\PurchaseOrders\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Products\Services\CodeGenerationService;
use Modules\PurchaseOrders\Models\Evaluation;
use Modules\PurchaseOrders\Models\EvaluationItem;
use Modules\PurchaseOrders\Models\EvaluationQuotation;
use Modules\PurchaseOrders\Models\EvaluationQuotationItem;

class EvaluationService
{
    public function __construct(private readonly CodeGenerationService $codes) {}

    /**
     * @param  array<string, mixed>  $data  from evaluationData()
     */
    public function create(array $data, User $user): Evaluation
    {
        return DB::transaction(function () use ($data, $user): Evaluation {
            $evaluation = Evaluation::create([
                'entity_id' => $user->entity_id,
                'code' => $this->codes->next('EVAL', Evaluation::class, $user->entity_id),
                'status' => 'draft',
                'recommendation_basis' => $data['recommendation_basis'] ?? null,
                'awarded_total' => 0,
                'created_by' => $user->getKey(),
                'updated_by' => $user->getKey(),
            ]);

            $this->syncMatrix($evaluation, $data, $user);

            return $evaluation->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data  from evaluationData()
     */
    public function update(Evaluation $evaluation, array $data, User $user): Evaluation
    {
        $this->ensureEditable($evaluation);

        return DB::transaction(function () use ($evaluation, $data, $user): Evaluation {
            $evaluation->update([
                'recommendation_basis' => $data['recommendation_basis'] ?? null,
                'updated_by' => $user->getKey(),
            ]);

            // Wholesale replace. forceDelete so the (quotation, item) unique
            // index doesn't collide with soft-deleted rows.
            $evaluation->quotationItems()->forceDelete();
            $evaluation->quotations()->forceDelete();
            $evaluation->items()->forceDelete();

            $this->syncMatrix($evaluation, $data, $user);

            return $evaluation->refresh();
        });
    }

    /**
     * An evaluation is editable only while it is not part of an active or
     * finished approval — approvals freeze the document so approvers decide
     * on exactly what they were shown.
     */
    public function ensureEditable(Evaluation $evaluation): void
    {
        if (in_array($evaluation->status, ['in_review', 'approved', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'status' => "An evaluation with status '{$evaluation->status}' cannot be modified.",
            ]);
        }
    }

    /**
     * While an approval is in flight the document cannot disappear under the
     * approvers' feet; decided evaluations keep their history.
     */
    public function ensureDeletable(Evaluation $evaluation): void
    {
        if ($evaluation->status === 'in_review') {
            throw ValidationException::withMessages([
                'status' => 'This evaluation is pending approval and cannot be deleted.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncMatrix(Evaluation $evaluation, array $data, User $user): void
    {
        $items = collect($data['items'])
            ->map(fn (array $row, int $index): EvaluationItem => EvaluationItem::create([
                'entity_id' => $evaluation->entity_id,
                'evaluation_id' => $evaluation->getKey(),
                'item_code' => $row['item_code'],
                'description' => $row['description'],
                'qty' => $row['qty'],
                'uom' => $row['uom'],
                'position' => $index,
            ]))
            ->values();

        $awarded = 0.0;

        foreach (array_values($data['quotations']) as $position => $quotationData) {
            $quotation = EvaluationQuotation::create([
                'entity_id' => $evaluation->entity_id,
                'evaluation_id' => $evaluation->getKey(),
                'supplier_code' => $quotationData['supplier_code'],
                'supplier_name' => $quotationData['supplier_name'],
                'supplier_phone' => $quotationData['supplier_phone'] ?? null,
                'supplier_address' => $quotationData['supplier_address'] ?? null,
                'discount' => $quotationData['discount'] ?? 0,
                'vat' => $quotationData['vat'] ?? 0,
                'price' => $quotationData['price'] ?? null,
                'quality' => $quotationData['quality'] ?? null,
                'lead_time' => $quotationData['lead_time'] ?? null,
                'warranty' => $quotationData['warranty'] ?? null,
                'payment_terms' => $quotationData['payment_terms'] ?? null,
                'other_remarks' => $quotationData['other_remarks'] ?? null,
                'subtotal' => 0,
                'grand_total' => 0,
                'position' => $position,
            ]);

            $subtotal = 0.0;

            foreach ($quotationData['lines'] as $line) {
                $item = $items[(int) $line['item_index']];
                $lineTotal = round((float) $item->qty * (float) $line['unit_cost'], 4);
                $selected = (bool) filter_var($line['is_selected'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $subtotal += $lineTotal;

                if ($selected) {
                    $awarded += $lineTotal;
                }

                EvaluationQuotationItem::create([
                    'entity_id' => $evaluation->entity_id,
                    'evaluation_id' => $evaluation->getKey(),
                    'evaluation_quotation_id' => $quotation->getKey(),
                    'evaluation_item_id' => $item->getKey(),
                    'brand' => $line['brand'] ?? null,
                    'unit_cost' => $line['unit_cost'],
                    'line_total' => $lineTotal,
                    'is_selected' => $selected,
                ]);
            }

            $discount = (float) ($quotationData['discount'] ?? 0);
            $vat = (float) ($quotationData['vat'] ?? 0);
            $quotation->update([
                'subtotal' => round($subtotal, 2),
                'grand_total' => round($subtotal - $discount + $vat, 2),
            ]);
        }

        $evaluation->update(['awarded_total' => round($awarded, 2)]);
    }
}
