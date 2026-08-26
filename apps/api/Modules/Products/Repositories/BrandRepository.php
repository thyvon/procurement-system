<?php

namespace Modules\Products\Repositories;

use App\Support\Repository\BaseRepository;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Modules\Products\Models\Brand;

class BrandRepository extends BaseRepository implements BrandRepositoryInterface
{
    public function __construct(Brand $model)
    {
        parent::__construct($model);
    }

    public function paginate(int $perPage = 20): CursorPaginator
    {
        return $this->query()->orderBy('name')->cursorPaginate($perPage);
    }
}
