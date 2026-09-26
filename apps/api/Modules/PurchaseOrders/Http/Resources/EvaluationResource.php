<?php

namespace Modules\PurchaseOrders\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Collection;
use Modules\PurchaseOrders\Models\Evaluation;

/**
 * @mixin Evaluation
 */
class EvaluationResource extends JsonResource
{
    /**
     * @return array{
     *     id: string,
     *     code: string,
     *     status: string|null,
     *     currency: string,
     *     exchangeRate: float,
     *     recommendationBasis: string|null,
     *     awardedTotal: float,
     *     updatedAt: string|null,
     *     createdBy: string|null,
     *     suppliers: list<array{code: string, name: string}>,
     *     items: MissingValue|Collection,
     *     quotations: MissingValue|Collection,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status,
            'currency' => (string) $this->currency,
            'exchangeRate' => (float) $this->exchange_rate,
            'recommendationBasis' => $this->recommendation_basis,
            'awardedTotal' => (float) $this->awarded_total,
            'updatedAt' => $this->updated_at?->format('Y-m-d H:i'),
            'createdBy' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'suppliers' => $this->winningSuppliers(),
            'items' => $this->relationLoaded('items')
                ? EvaluationItemResource::collection($this->items)
                : new MissingValue,
            'quotations' => $this->relationLoaded('quotations')
                ? EvaluationQuotationResource::collection($this->quotations)
                : new MissingValue,
        ];
    }

    /**
     * Winners: quotations with at least one selected line.
     *
     * @return list<array{code: string, name: string}>
     */
    public function winningSuppliers(): array
    {
        if (! $this->relationLoaded('quotations')) {
            return [];
        }

        $winners = [];
        foreach ($this->quotations as $quotation) {
            if (! $quotation->relationLoaded('lines') || ! $quotation->lines->contains('is_selected', true)) {
                continue;
            }

            $winners[] = [
                'code' => (string) $quotation->supplier_code,
                'name' => (string) $quotation->supplier_name,
            ];
        }

        return $winners;
    }
}
