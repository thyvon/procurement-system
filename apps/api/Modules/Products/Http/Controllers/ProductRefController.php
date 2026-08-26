<?php

namespace Modules\Products\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Products\Models\Brand;
use Modules\Products\Models\ProductCategory;
use Modules\Products\Models\ProductGroup;
use Modules\Products\Repositories\ProductRepositoryInterface;
use Modules\Products\Repositories\UomRepositoryInterface;

/**
 * Dropdown feeds for product forms — one round trip instead of five.
 */
class ProductRefController extends Controller
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly UomRepositoryInterface $uoms,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = [
            'categories' => ProductCategory::query()
                ->orderBy('sort_order')->orderBy('name')
                ->get(['id', 'parent_id', 'code', 'name', 'name_km']),
            'groups' => ProductGroup::query()->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'uoms' => $this->uoms->allWithSubUnits(),
        ];

        if ($request->boolean('with_products')) {
            $data['products'] = collect($this->products->options());
        }

        return ApiResponse::success($data);
    }
}
