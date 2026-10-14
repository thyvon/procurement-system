<?php

namespace Modules\Organization\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared authorization shape for organization-module lookups: holders of
 * `<module>.view` read; holders of `<module>.manage` write (see
 * PermissionSeeder). Model parameters are nullable so the same policies
 * serve instance and class-string abilities.
 */
abstract class OrgLookupPolicy
{
    /**
     * Permission vocabulary prefix for this policy's model group
     * (e.g. "campuses" → campuses.view / campuses.manage).
     */
    abstract protected function permissionModule(): string;

    public function viewAny(User $user): bool
    {
        return $user->can($this->permissionModule().'.view', 'sanctum');
    }

    public function view(User $user, ?Model $model = null): bool
    {
        return $user->can($this->permissionModule().'.view', 'sanctum');
    }

    public function create(User $user): bool
    {
        return $user->can($this->permissionModule().'.manage', 'sanctum');
    }

    public function update(User $user, ?Model $model = null): bool
    {
        return $user->can($this->permissionModule().'.manage', 'sanctum');
    }

    public function delete(User $user, ?Model $model = null): bool
    {
        return $user->can($this->permissionModule().'.manage', 'sanctum');
    }
}
