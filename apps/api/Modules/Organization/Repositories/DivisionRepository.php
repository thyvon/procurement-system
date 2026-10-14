<?php

namespace Modules\Organization\Repositories;

use App\Support\Repository\BaseRepository;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Modules\Organization\Models\Division;

class DivisionRepository extends BaseRepository implements DivisionRepositoryInterface
{
    public function __construct(Division $model)
    {
        parent::__construct($model);
    }

    public function paginate(int $perPage = 20): CursorPaginator
    {
        return $this->query()->with('departments:id')->orderBy('name')->cursorPaginate($perPage);
    }
}
