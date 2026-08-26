<?php

namespace Modules\Products\Repositories;

use App\Support\Repository\BaseRepository;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Modules\Products\Models\Uom;

class UomRepository extends BaseRepository implements UomRepositoryInterface
{
    public function __construct(Uom $model)
    {
        parent::__construct($model);
    }

    public function paginate(int $perPage = 20): CursorPaginator
    {
        return $this->query()->with('subUnits')->orderBy('name')->cursorPaginate($perPage);
    }

    public function allWithSubUnits(): array
    {
        return $this->query()->with('subUnits')->orderBy('name')->get()->all();
    }
}
