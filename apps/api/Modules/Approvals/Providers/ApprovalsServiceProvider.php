<?php

namespace Modules\Approvals\Providers;

use Illuminate\Support\Facades\Gate;
use Modules\Approvals\Models\ApprovalFlow;
use Modules\Approvals\Models\ApprovalRequest;
use Modules\Approvals\Models\ApprovalSetting;
use Modules\Approvals\Models\TocaEntry;
use Modules\Approvals\Policies\ApprovalFlowPolicy;
use Modules\Approvals\Policies\ApprovalPolicy;
use Modules\Approvals\Policies\ApprovalSettingPolicy;
use Modules\Approvals\Policies\TocaEntryPolicy;
use Modules\Approvals\Repositories\ApprovalRequestRepository;
use Modules\Approvals\Repositories\ApprovalRequestRepositoryInterface;
use Modules\Approvals\Repositories\TocaEntryRepository;
use Modules\Approvals\Repositories\TocaEntryRepositoryInterface;
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
        $this->app->bind(TocaEntryRepositoryInterface::class, TocaEntryRepository::class);
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(ApprovalRequest::class, ApprovalPolicy::class);
        Gate::policy(ApprovalSetting::class, ApprovalSettingPolicy::class);
        Gate::policy(ApprovalFlow::class, ApprovalFlowPolicy::class);
        Gate::policy(TocaEntry::class, TocaEntryPolicy::class);
    }
}
