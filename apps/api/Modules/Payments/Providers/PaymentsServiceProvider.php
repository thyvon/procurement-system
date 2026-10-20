<?php

namespace Modules\Payments\Providers;

use Illuminate\Support\Facades\Gate;
use Modules\Payments\Models\AdvanceLiquidation;
use Modules\Payments\Models\CashClearance;
use Modules\Payments\Models\CashRequest;
use Modules\Payments\Policies\AdvanceLiquidationPolicy;
use Modules\Payments\Policies\CashClearancePolicy;
use Modules\Payments\Policies\CashRequestPolicy;
use Modules\Payments\Repositories\AdvanceLiquidationRepository;
use Modules\Payments\Repositories\AdvanceLiquidationRepositoryInterface;
use Modules\Payments\Repositories\CashClearanceRepository;
use Modules\Payments\Repositories\CashClearanceRepositoryInterface;
use Modules\Payments\Repositories\CashRequestRepository;
use Modules\Payments\Repositories\CashRequestRepositoryInterface;
use Nwidart\Modules\Support\ModuleServiceProvider;

class PaymentsServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Payments';

    protected string $nameLower = 'payments';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(
            CashRequestRepositoryInterface::class,
            CashRequestRepository::class,
        );
        $this->app->bind(
            CashClearanceRepositoryInterface::class,
            CashClearanceRepository::class,
        );
        $this->app->bind(
            AdvanceLiquidationRepositoryInterface::class,
            AdvanceLiquidationRepository::class,
        );
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(CashRequest::class, CashRequestPolicy::class);
        Gate::policy(CashClearance::class, CashClearancePolicy::class);
        Gate::policy(AdvanceLiquidation::class, AdvanceLiquidationPolicy::class);
    }
}
