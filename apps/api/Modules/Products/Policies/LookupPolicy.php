<?php

namespace Modules\Products\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared authorization shape for product-module entities: holders of
 * `<module>.view` read; holders of `<module>.manage` write. Each concrete
 * policy names its module in the permission vocabulary (see PermissionSeeder).
 *
 * Model parameters are nullable so the same policies serve both
 * instance abilities (`update($user, $model)`) and class-string
 * abilities (`update($user, Model::class)`).
 */
abstract class LookupPolicy
{
    /**
     * Permission vocabulary prefix for this policy's model group
     * (e.g. "brands" → brands.view / brands.manage).
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
