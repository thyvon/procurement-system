<?php

namespace Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'guardName' => $this->guard_name,
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('name')->values()),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
