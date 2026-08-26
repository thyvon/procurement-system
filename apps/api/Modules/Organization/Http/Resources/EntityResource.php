<?php

namespace Modules\Organization\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Organization\Models\Entity;

/**
 * @mixin Entity
 */
class EntityResource extends JsonResource
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
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'isActive' => $this->is_active,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
