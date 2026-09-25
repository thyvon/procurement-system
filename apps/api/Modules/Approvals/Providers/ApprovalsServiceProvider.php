<?php

namespace Modules\Approvals\Providers;

use Illuminate\Support\Facades\Gate;
use Modules\Approvals\Models\ApprovalRequest;
use Modules\Approvals\Policies\ApprovalPolicy;
use Modules\Approvals\Repositories\ApprovalRequestRepository;
use Modules\Approvals\Repositories\ApprovalRequestRepositoryInterface;
use Nwidart\Modules\Support\ModuleServiceProvider;

class ApprovalsServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Approvals';

    protected string $nameLower = 'approvals';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(ApprovalRequestRepositoryInterface::class, ApprovalRequestRepository::class);
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(ApprovalRequest::class, ApprovalPolicy::class);
    }
}
