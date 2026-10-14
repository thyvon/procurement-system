<?php

namespace Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Modules\Organization\Models\Entity;

class BrandingController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $entity = Entity::query()
            ->where('is_active', true)
            ->orderBy('created_at')
            ->first();

        return ApiResponse::success([
            'name' => $entity?->name,
            'logo' => $this->logoUrl($entity),
        ]);
    }

    /**
     * Scramble cannot infer Storage::url()'s return type, which collapses the
     * ternary to a null-only union; the tag pins the real contract instead.
     *
     * @scramble-return string|null
     */
    private function logoUrl(?Entity $entity): ?string
    {
        return $entity?->logo_path !== null
            ? Storage::disk('public')->url($entity->logo_path)
            : null;
    }
}
