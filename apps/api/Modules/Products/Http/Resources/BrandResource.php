<?php

namespace Modules\Products\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Products\Models\Brand;

/**
 * @mixin Brand
 */
class BrandResource extends JsonResource
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
            'description' => $this->description,
            'isActive' => $this->is_active,
        ];
    }
}
