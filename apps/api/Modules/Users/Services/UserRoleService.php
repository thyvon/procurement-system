<?php

namespace Modules\Users\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Contracts\RevokesUserTokens;
use Spatie\Permission\Models\Role;

class UserRoleService
{
    public function __construct(private readonly RevokesUserTokens $tokenRevoker) {}

    /**
     * @param  array<int, string>  $roleNames
     */
    public function syncRoles(User $user, array $roleNames): void
    {
        $roles = Role::query()
            ->where('guard_name', 'sanctum')
            ->whereIn('name', $roleNames)
            ->get();

        $user->syncRoles($roles->all());
    }

    public function deactivate(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->forceFill(['is_active' => false])->save();
            $user->tokens()->delete();
        });

        $this->tokenRevoker->revokeAllFor($user);
    }
}
