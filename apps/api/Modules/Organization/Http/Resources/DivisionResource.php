<?php

namespace Modules\Organization\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Organization\Models\Division;

/**
 * @mixin Division
 */
class DivisionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'isActive' => $this->is_active,
            'departmentIds' => $this->whenLoaded(
                'departments',
                fn () => $this->departments->pluck('id')->values()
            ),
        ];
    }
}
