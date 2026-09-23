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
use Modules\Products\Services\CodeGenerationService;

class ProductGroupController extends Controller
{
    use Concerns\InteractsWithLookups;

    public function __construct(
        private readonly ProductGroupRepositoryInterface $repo,
        private readonly CodeGenerationService $codes,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return $this->doIndex();
    }

    public function store(StoreProductGroupRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['code'] = $this->codes->next('GRP', ProductGroup::class, $request->user()?->entity_id);

        return $this->doStore($data);
    }

    public function show(ProductGroup $group): ProductGroupResource
    {
        $this->authorize('view', $group);

        return new ProductGroupResource($group);
    }

    public function update(UpdateProductGroupRequest $request, ProductGroup $group): mixed
    {
        return $this->doUpdate($group, $request->validated());
    }

    public function destroy(ProductGroup $group): JsonResponse
    {
        return $this->doDestroy($group);
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
