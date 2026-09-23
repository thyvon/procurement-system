<?php

namespace Modules\Users\Repositories;

use App\Support\Repository\BaseRepository;
use Spatie\Permission\Models\Role;

class RoleRepository extends BaseRepository implements RoleRepositoryInterface
{
    public function __construct(Role $model)
    {
        parent::__construct($model);
    }

    /**
     * @return array<int, Role>
     */
    public function allForGuard(string $guard = 'sanctum'): array
    {
        return $this->query()
            ->where('guard_name', $guard)
            ->with('permissions')
            ->orderBy('name')
            ->get()
            ->all();
    }
}
