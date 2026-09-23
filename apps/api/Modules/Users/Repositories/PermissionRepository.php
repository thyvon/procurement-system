<?php

namespace Modules\Users\Repositories;

use App\Support\Repository\BaseRepository;
use Spatie\Permission\Models\Permission;

class PermissionRepository extends BaseRepository implements PermissionRepositoryInterface
{
    public function __construct(Permission $model)
    {
        parent::__construct($model);
    }

    /**
     * @return array<int, Permission>
     */
    public function allForGuard(string $guard = 'sanctum'): array
    {
        return $this->query()
            ->where('guard_name', $guard)
            ->orderBy('name')
            ->get()
            ->all();
    }
}
