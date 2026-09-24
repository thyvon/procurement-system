<?php

namespace Modules\PurchaseOrders\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\PurchaseOrders\Models\EvaluationQuotationItem;

/**
 * @mixin EvaluationQuotationItem
 */
class EvaluationQuotationItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'itemId' => $this->evaluation_item_id,
            'brand' => $this->brand,
            'unitCost' => (float) $this->unit_cost,
            'lineTotal' => (float) $this->line_total,
            'isSelected' => (bool) $this->is_selected,
        ];
    }
}
