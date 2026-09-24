<?php

namespace Modules\Products\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Modules\Products\Http\Requests\IndexProductsRequest;
use Modules\Products\Http\Requests\StoreProductRequest;
use Modules\Products\Http\Requests\UpdateProductRequest;
use Modules\Products\Http\Resources\ProductResource;
use Modules\Products\Models\Product;
use Modules\Products\Repositories\ProductRepositoryInterface;
use Modules\Products\Services\CodeGenerationService;
use Modules\Products\Services\VariationService;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductRepositoryInterface $repo,
        private readonly CodeGenerationService $codes,
        private readonly VariationService $variation,
    ) {}

    public function index(IndexProductsRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Product::class);

        $paginator = $this->repo->filtered([
            'search' => $request->string('search')->toString(),
            'category_id' => $request->string('category_id')->toString(),
            'group_id' => $request->string('group_id')->toString(),
            'brand_id' => $request->string('brand_id')->toString(),
            'status' => $request->string('status')->toString(),
            'type' => $request->string('type')->toString(),
        ], (int) $request->query('per_page', '20'));

        return ProductResource::collection($paginator);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        /** @var User $user */
        $user = $request->user();

        $data = $request->productData();
        $data['entity_id'] = $user->entity_id;
        $data['created_by'] = $user->getKey();
        $data['updated_by'] = $user->getKey();

        $data['code'] = trim((string) ($data['code'] ?? '')) !== ''
            ? $data['code']
            : $this->codes->next('PRD', Product::class, $user->entity_id);

        $templateIds = $request->templateIds();
        $variants = $request->variantRows();

        $product = DB::transaction(function () use ($data, $templateIds, $variants) {
            /** @var Product $product */
            $product = $this->repo->create($data);

            return $this->variation->applyToProduct($product, $templateIds, $variants);
        });

        return ApiResponse::success(new ProductResource($product->load([
            'category',
            'group',
            'brand',
            'uom',
            'variants',
            'variationTemplates',
        ])), 201);
    }

    public function show(Product $product): ProductResource
    {
        $this->authorize('view', $product);

        return new ProductResource($product->load([
            'category',
            'group',
            'brand',
            'uom',
            'variants',
            'variationTemplates',
        ]));
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $this->authorize('update', $product);

        /** @var User $user */
        $user = $request->user();

        $data = $request->productData();
        if (in_array('updated_by', $product->getFillable(), true)) {
            $data['updated_by'] = $user->getKey();
        }

        $templateIds = $request->templateIds();
        $variants = $request->variantRows();
        $shouldSync = $request->shouldSyncVariation();

        $product = DB::transaction(function () use ($product, $data, $templateIds, $variants, $shouldSync) {
            $this->repo->update($product, $data);

            if ($shouldSync) {
                return $this->variation->applyToProduct($product, $templateIds, $variants);
            }

            return $product->load([
                'category',
                'group',
                'brand',
                'uom',
                'variants',
                'variationTemplates',
            ]);
        });

        return new ProductResource($product);
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        $this->repo->delete($product);

        return ApiResponse::success(['deleted' => true]);
    }
}
