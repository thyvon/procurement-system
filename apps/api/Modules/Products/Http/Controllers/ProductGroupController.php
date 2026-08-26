<?php

namespace Modules\Products\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Products\Http\Requests\StoreProductGroupRequest;
use Modules\Products\Http\Requests\UpdateProductGroupRequest;
use Modules\Products\Http\Resources\ProductGroupResource;
use Modules\Products\Models\ProductGroup;
use Modules\Products\Repositories\ProductGroupRepositoryInterface;

class ProductGroupController extends Controller
{
    use Concerns\InteractsWithLookups;

    public function __construct(private readonly ProductGroupRepositoryInterface $repo) {}

    public function index(): AnonymousResourceCollection
    {
        return $this->doIndex();
    }

    public function store(StoreProductGroupRequest $request): JsonResponse
    {
        return $this->doStore($request->validated());
    }

    public function show(ProductGroup $brand): ProductGroupResource
    {
        $this->authorize('view', $brand);

        return new ProductGroupResource($brand);
    }

    public function update(UpdateProductGroupRequest $request, ProductGroup $brand): mixed
    {
        return $this->doUpdate($brand, $request->validated());
    }

    public function destroy(ProductGroup $brand): JsonResponse
    {
        return $this->doDestroy($brand);
    }

    protected function repo(): ProductGroupRepositoryInterface
    {
        return $this->repo;
    }

    protected function modelClass(): string
    {
        return ProductGroup::class;
    }

    protected function resourceClass(): string
    {
        return ProductGroupResource::class;
    }
}
