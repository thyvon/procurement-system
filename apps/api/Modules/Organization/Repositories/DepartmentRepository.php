<?php

namespace Modules\Organization\Repositories;

use App\Support\Repository\BaseRepository;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Modules\Organization\Models\Department;

class DepartmentRepository extends BaseRepository implements DepartmentRepositoryInterface
{
    public function __construct(Department $model)
    {
        parent::__construct($model);
    }

    public function paginate(int $perPage = 20): CursorPaginator
    {
        return $this->query()->with('divisions:id')->orderBy('name')->cursorPaginate($perPage);
    }
}
