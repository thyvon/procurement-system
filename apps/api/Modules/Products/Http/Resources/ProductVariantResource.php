<?php

namespace Modules\Products\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Products\Models\ProductVariant;

/**
 * @mixin ProductVariant
 */
class ProductVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'productId' => $this->product_id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'optionValues' => $this->option_values,
            'subUnitId' => $this->sub_unit_id,
            'purchasePrice' => $this->purchase_price !== null ? (float) $this->purchase_price : null,
            'subUnitPurchasePrice' => $this->sub_unit_purchase_price !== null ? (float) $this->sub_unit_purchase_price : null,
            'imageUrl' => $this->image_url,
            'isActive' => $this->is_active,
        ];
    }
}
