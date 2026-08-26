<?php

namespace Modules\Products\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Products\Models\UomSubUnit;

/**
 * @mixin UomSubUnit
 */
class UomSubUnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uomId' => $this->uom_id,
            'name' => $this->name,
            'shortName' => $this->short_name,
            'conversionFactor' => (float) $this->conversion_factor,
            'isActive' => $this->is_active,
        ];
    }
}
