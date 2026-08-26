<?php

namespace Modules\Organization\Policies;

use App\Models\User;
use Modules\Organization\Models\Entity;

class EntityPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Entity $entity): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Entity $entity): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Entity $entity): bool
    {
        return $user->hasRole('admin');
    }
}
