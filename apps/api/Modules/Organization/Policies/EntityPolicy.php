<?php

namespace Modules\Organization\Policies;

use App\Models\User;
use Modules\Organization\Models\Entity;

class EntityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('entities.view', 'sanctum');
    }

    public function view(User $user, Entity $entity): bool
    {
        return $user->can('entities.view', 'sanctum');
    }

    public function create(User $user): bool
    {
        return $user->can('entities.manage', 'sanctum');
    }

    public function update(User $user, Entity $entity): bool
    {
        return $user->can('entities.manage', 'sanctum');
    }

    public function delete(User $user, Entity $entity): bool
    {
        return $user->can('entities.manage', 'sanctum');
    }
}
