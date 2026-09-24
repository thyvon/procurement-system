<?php

namespace Modules\PurchaseOrders\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\PurchaseOrders\Models\EvaluationItem;

/**
 * @mixin EvaluationItem
 */
class EvaluationItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'itemCode' => $this->item_code,
            'description' => $this->description,
            'qty' => (float) $this->qty,
            'uom' => $this->uom,
            'position' => $this->position,
        ];
    }
}
