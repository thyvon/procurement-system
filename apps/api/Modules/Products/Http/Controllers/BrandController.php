<?php

namespace Modules\Products\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Products\Http\Requests\StoreBrandRequest;
use Modules\Products\Http\Requests\UpdateBrandRequest;
use Modules\Products\Http\Resources\BrandResource;
use Modules\Products\Models\Brand;
use Modules\Products\Repositories\BrandRepositoryInterface;
use Modules\Products\Services\CodeGenerationService;

class BrandController extends Controller
{
    use Concerns\InteractsWithLookups;

    public function __construct(
        private readonly BrandRepositoryInterface $repo,
        private readonly CodeGenerationService $codes,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return $this->doIndex();
    }

    public function store(StoreBrandRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['code'] = $this->codes->next('BRD', Brand::class, $request->user()?->entity_id);

        return $this->doStore($data);
    }

    public function show(Brand $brand): BrandResource
    {
        $this->authorize('view', $brand);

        return new BrandResource($brand);
    }

    public function update(UpdateBrandRequest $request, Brand $brand): mixed
    {
        return $this->doUpdate($brand, $request->validated());
    }

    public function destroy(Brand $brand): JsonResponse
    {
        return $this->doDestroy($brand);
    }

    protected function repo(): BrandRepositoryInterface
    {
        return $this->repo;
    }

    protected function modelClass(): string
    {
        return Brand::class;
    }

    protected function resourceClass(): string
    {
        return BrandResource::class;
    }
}
