<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatarUrl(),
            'entityId' => $this->entity_id,
            'isActive' => $this->is_active ?? null,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->values()),
            'permissions' => $this->when(
                $request->user()?->is($this->resource) ?? false,
                function () use ($request) {
                    $user = $request->user();
                    $user->loadMissing(['permissions', 'roles.permissions']);

                    return $user->getAllPermissions()->pluck('name')->values();
                },
            ),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Scramble cannot infer Storage::url()'s return type, which collapses the
     * ternary to a null-only union; the tag pins the real contract instead.
     *
     * @scramble-return string|null
     */
    private function avatarUrl(): ?string
    {
        return $this->avatar_path !== null
            ? Storage::disk('public')->url($this->avatar_path)
            : null;
    }
}
