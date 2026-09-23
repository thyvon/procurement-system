<?php

namespace Modules\Users\Repositories;

use App\Support\Repository\RepositoryInterface;
use Spatie\Permission\Models\Permission;

interface PermissionRepositoryInterface extends RepositoryInterface
{
    /**
     * @return array<int, Permission>
     */
    public function allForGuard(string $guard = 'sanctum'): array;
}
