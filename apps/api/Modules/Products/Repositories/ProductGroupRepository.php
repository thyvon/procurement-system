<?php

namespace Modules\Products\Repositories;

use App\Support\Repository\BaseRepository;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Modules\Products\Models\ProductGroup;

class ProductGroupRepository extends BaseRepository implements ProductGroupRepositoryInterface
{
    public function __construct(ProductGroup $model)
    {
        parent::__construct($model);
    }

    public function paginate(int $perPage = 20): CursorPaginator
    {
        return $this->query()->orderBy('name')->cursorPaginate($perPage);
    }
}
