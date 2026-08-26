<?php

namespace Modules\Organization\Providers;

use Illuminate\Support\Facades\Gate;
use Modules\Organization\Models\Entity;
use Modules\Organization\Policies\EntityPolicy;
use Modules\Organization\Repositories\EntityRepository;
use Modules\Organization\Repositories\EntityRepositoryInterface;
use Nwidart\Modules\Support\ModuleServiceProvider;

class OrganizationServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Organization';

    protected string $nameLower = 'organization';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(EntityRepositoryInterface::class, EntityRepository::class);
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Entity::class, EntityPolicy::class);
    }
}
