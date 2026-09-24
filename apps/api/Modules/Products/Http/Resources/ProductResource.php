<?php

namespace Modules\Products\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;
use Modules\Products\Models\Product;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'nameKm' => $this->name_km,
            'description' => $this->description,
            'productType' => $this->product_type,
            'categoryId' => $this->product_category_id,
            'categoryName' => $this->whenLoaded('category', fn () => $this->category?->name),
            'groupId' => $this->product_group_id,
            'groupName' => $this->whenLoaded('group', fn () => $this->group?->name),
            'brandId' => $this->brand_id,
            'brandName' => $this->whenLoaded('brand', fn () => $this->brand?->name),
            'uomId' => $this->uom_id,
            'uomShortName' => $this->whenLoaded('uom', fn () => $this->uom?->short_name),
            'subUnitId' => $this->sub_unit_id,
            'purchasePrice' => $this->purchase_price !== null ? (float) $this->purchase_price : null,
            'subUnitPurchasePrice' => $this->sub_unit_purchase_price !== null ? (float) $this->sub_unit_purchase_price : null,
            'imageUrl' => $this->image_url,
            'isActive' => $this->is_active,
            'variantCount' => $this->whenCounted('variants'),
            'variants' => $this->relationLoaded('variants')
                ? ProductVariantResource::collection($this->variants)
                : new MissingValue,
            'templateIds' => $this->relationLoaded('variationTemplates')
                ? $this->variationTemplates->pluck('id')->values()->all()
                : new MissingValue,
        ];
    }
}
