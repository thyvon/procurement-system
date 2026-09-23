<?php

namespace Modules\Users\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\Users\Policies\PermissionPolicy;
use Modules\Users\Policies\RolePolicy;
use Modules\Users\Policies\UserPolicy;
use Modules\Users\Repositories\PermissionRepository;
use Modules\Users\Repositories\PermissionRepositoryInterface;
use Modules\Users\Repositories\RoleRepository;
use Modules\Users\Repositories\RoleRepositoryInterface;
use Modules\Users\Repositories\UserRepository;
use Modules\Users\Repositories\UserRepositoryInterface;
use Nwidart\Modules\Support\ModuleServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UsersServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Users';

    protected string $nameLower = 'users';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(RoleRepositoryInterface::class, RoleRepository::class);
        $this->app->bind(PermissionRepositoryInterface::class, PermissionRepository::class);
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
    }
}
