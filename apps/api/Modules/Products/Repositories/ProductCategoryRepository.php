<?php

namespace Modules\Products\Repositories;

use App\Support\Repository\BaseRepository;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Modules\Products\Models\ProductCategory;

class ProductCategoryRepository extends BaseRepository implements ProductCategoryRepositoryInterface
{
    public function __construct(ProductCategory $model)
    {
        parent::__construct($model);
    }

    public function paginate(int $perPage = 20): CursorPaginator
    {
        return $this->query()
            ->with('parent')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->cursorPaginate($perPage);
    }

    public function tree(): array
    {
        return $this->query()
            ->with('children')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->all();
    }
}
