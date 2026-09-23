<?php

namespace Modules\Products\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Products\Models\ProductCategory;

/**
 * @mixin ProductCategory
 */
class ProductCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parentId' => $this->parent_id,
            'code' => $this->code,
            'shortCode' => $this->short_code,
            'name' => $this->name,
            'nameKm' => $this->name_km,
            'sortOrder' => $this->sort_order,
            'isActive' => $this->is_active,
            'children' => ProductCategoryResource::collection($this->whenLoaded('children')),
        ];
    }
}
