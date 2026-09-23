<?php

namespace Modules\Products\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Modules\Products\Http\Requests\StoreProductCategoryRequest;
use Modules\Products\Http\Requests\UpdateProductCategoryRequest;
use Modules\Products\Http\Resources\ProductCategoryResource;
use Modules\Products\Models\ProductCategory;
use Modules\Products\Repositories\ProductCategoryRepositoryInterface;
use Modules\Products\Services\CodeGenerationService;

class ProductCategoryController extends Controller
{
    use Concerns\InteractsWithLookups;

    public function __construct(
        private readonly ProductCategoryRepositoryInterface $repo,
        private readonly CodeGenerationService $codes,
    ) {}

    public function tree(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ProductCategory::class);

        return ProductCategoryResource::collection(collect($this->repo->tree()));
    }

    public function index(): AnonymousResourceCollection
    {
        return $this->doIndex();
    }

    public function store(StoreProductCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();

        $data['code'] = $this->codes->next('CAT', ProductCategory::class, $request->user()?->entity_id);
        $data['short_code'] ??= Str::upper(Str::substr($data['name'], 0, 10));

        return $this->doStore($data);
    }

    public function show(ProductCategory $category): ProductCategoryResource
    {
        $this->authorize('view', $category);

        return new ProductCategoryResource($category);
    }

    public function update(UpdateProductCategoryRequest $request, ProductCategory $category): mixed
    {
        return $this->doUpdate($category, $request->validated());
    }

    public function destroy(ProductCategory $category): JsonResponse
    {
        return $this->doDestroy($category);
    }

    protected function repo(): ProductCategoryRepositoryInterface
    {
        return $this->repo;
    }

    protected function modelClass(): string
    {
        return ProductCategory::class;
    }

    protected function resourceClass(): string
    {
        return ProductCategoryResource::class;
    }
}
