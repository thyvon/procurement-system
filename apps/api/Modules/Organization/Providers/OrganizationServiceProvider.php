<?php

namespace Modules\Organization\Providers;

use Illuminate\Support\Facades\Gate;
use Modules\Organization\Models\Campus;
use Modules\Organization\Models\Department;
use Modules\Organization\Models\Division;
use Modules\Organization\Models\Entity;
use Modules\Organization\Models\SystemSetting;
use Modules\Organization\Policies\CampusPolicy;
use Modules\Organization\Policies\DepartmentPolicy;
use Modules\Organization\Policies\DivisionPolicy;
use Modules\Organization\Policies\EntityPolicy;
use Modules\Organization\Policies\SystemSettingPolicy;
use Modules\Organization\Repositories\CampusRepository;
use Modules\Organization\Repositories\CampusRepositoryInterface;
use Modules\Organization\Repositories\DepartmentRepository;
use Modules\Organization\Repositories\DepartmentRepositoryInterface;
use Modules\Organization\Repositories\DivisionRepository;
use Modules\Organization\Repositories\DivisionRepositoryInterface;
use Modules\Organization\Repositories\EntityRepository;
use Modules\Organization\Repositories\EntityRepositoryInterface;
use Modules\Organization\Services\SystemSettingsService;
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
        $this->app->bind(CampusRepositoryInterface::class, CampusRepository::class);
        $this->app->bind(DivisionRepositoryInterface::class, DivisionRepository::class);
        $this->app->bind(DepartmentRepositoryInterface::class, DepartmentRepository::class);

        // Singleton so boot-time overlay state (what config values were
        // superseded) survives into controllers serving the same process.
        $this->app->singleton(SystemSettingsService::class);
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Entity::class, EntityPolicy::class);
        Gate::policy(Campus::class, CampusPolicy::class);
        Gate::policy(Division::class, DivisionPolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(SystemSetting::class, SystemSettingPolicy::class);

        app(SystemSettingsService::class)->overlayConfig();
    }
}
