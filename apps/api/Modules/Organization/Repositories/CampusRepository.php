<?php

namespace Modules\Organization\Repositories;

use App\Support\Repository\BaseRepository;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Modules\Organization\Models\Campus;

class CampusRepository extends BaseRepository implements CampusRepositoryInterface
{
    public function __construct(Campus $model)
    {
        parent::__construct($model);
    }

    public function paginate(int $perPage = 20): CursorPaginator
    {
        return $this->query()->orderBy('name')->cursorPaginate($perPage);
    }
}
