<?php

namespace Modules\PurchaseOrders\Providers;

use Illuminate\Support\Facades\Gate;
use Modules\PurchaseOrders\Models\PurchaseOrder;
use Modules\PurchaseOrders\Policies\PurchaseOrderPolicy;
use Modules\PurchaseOrders\Repositories\PurchaseOrderRepository;
use Modules\PurchaseOrders\Repositories\PurchaseOrderRepositoryInterface;
use Nwidart\Modules\Support\ModuleServiceProvider;

class PurchaseOrdersServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'PurchaseOrders';

    protected string $nameLower = 'purchase-orders';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(PurchaseOrderRepositoryInterface::class, PurchaseOrderRepository::class);
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
    }
}
