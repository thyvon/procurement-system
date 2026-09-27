<?php

namespace Modules\Organization\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
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
            'logo' => $this->logoUrl(),
            'isActive' => $this->is_active,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Scramble cannot infer Storage::url()'s return type, which collapses the
     * ternary to a null-only union; the tag pins the real contract instead.
     *
     * @scramble-return string|null
     */
    private function logoUrl(): ?string
    {
        return $this->logo_path !== null
            ? Storage::disk('public')->url($this->logo_path)
            : null;
    }
}
