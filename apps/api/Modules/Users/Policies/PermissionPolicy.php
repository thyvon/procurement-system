<?php

namespace Modules\Users\Policies;

use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * The permission vocabulary is defined in code (PermissionSeeder) — any
 * authenticated user may read it so the Roles UI can render its matrix;
 * it is never writable through the API.
 */
class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Permission $permission): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Permission $permission): bool
    {
        return false;
    }

    public function delete(User $user, Permission $permission): bool
    {
        return false;
    }
}
