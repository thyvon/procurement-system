<?php

namespace Modules\Users\Repositories;

use App\Support\Repository\RepositoryInterface;
use Spatie\Permission\Models\Role;

interface RoleRepositoryInterface extends RepositoryInterface
{
    /**
     * @return array<int, Role>
     */
    public function allForGuard(string $guard = 'sanctum'): array;
}
