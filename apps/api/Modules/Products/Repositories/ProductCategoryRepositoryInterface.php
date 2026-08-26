<?php

namespace Modules\Products\Repositories;

use App\Support\Repository\RepositoryInterface;
use Modules\Products\Models\ProductCategory;

interface ProductCategoryRepositoryInterface extends RepositoryInterface
{
    /**
     * Root categories (no parent) with children eager-loaded, ready to render as a tree.
     *
     * @return array<int, ProductCategory>
     */
    public function tree(): array;
}
