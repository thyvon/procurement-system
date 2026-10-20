<?php

namespace Modules\Organization\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Organization\Models\Department;

/**
 * @mixin Department
 */
class DepartmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'shortCode' => $this->short_code,
            'isActive' => $this->is_active,
            'divisionIds' => $this->whenLoaded(
                'divisions',
                fn () => $this->divisions->pluck('id')->values()
            ),
        ];
    }
}
