<?php

namespace Modules\Users\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('roles.view', 'sanctum');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('roles.view', 'sanctum');
    }

    public function create(User $user): bool
    {
        return $user->can('roles.manage', 'sanctum');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can('roles.manage', 'sanctum');
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can('roles.manage', 'sanctum');
    }
}
