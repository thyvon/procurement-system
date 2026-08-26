<?php

namespace Modules\Users\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\Users\Policies\UserPolicy;
use Modules\Users\Repositories\UserRepository;
use Modules\Users\Repositories\UserRepositoryInterface;
use Nwidart\Modules\Support\ModuleServiceProvider;

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
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(User::class, UserPolicy::class);
    }
}
