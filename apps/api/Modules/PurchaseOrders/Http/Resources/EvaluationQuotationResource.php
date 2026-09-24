<?php

namespace Modules\PurchaseOrders\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;
use Modules\PurchaseOrders\Models\EvaluationQuotation;

/**
 * @mixin EvaluationQuotation
 */
class EvaluationQuotationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplierCode' => $this->supplier_code,
            'supplierName' => $this->supplier_name,
            'supplierPhone' => $this->supplier_phone,
            'supplierAddress' => $this->supplier_address,
            'discount' => (float) $this->discount,
            'vat' => (float) $this->vat,
            'subtotal' => (float) $this->subtotal,
            'grandTotal' => (float) $this->grand_total,
            'price' => $this->price,
            'quality' => $this->quality,
            'leadTime' => $this->lead_time,
            'warranty' => $this->warranty,
            'paymentTerms' => $this->payment_terms,
            'position' => $this->position,
            'lines' => $this->relationLoaded('lines')
                ? EvaluationQuotationItemResource::collection($this->lines)
                : new MissingValue,
        ];
    }
}
