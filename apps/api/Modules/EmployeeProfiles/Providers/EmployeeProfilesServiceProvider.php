<?php

namespace Modules\EmployeeProfiles\Providers;

use Modules\EmployeeProfiles\Repositories\EmployeeProfileRepository;
use Modules\EmployeeProfiles\Repositories\EmployeeProfileRepositoryInterface;
use Nwidart\Modules\Support\ModuleServiceProvider;

class EmployeeProfilesServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'EmployeeProfiles';

    protected string $nameLower = 'employeeprofiles';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(EmployeeProfileRepositoryInterface::class, EmployeeProfileRepository::class);
    }
}
