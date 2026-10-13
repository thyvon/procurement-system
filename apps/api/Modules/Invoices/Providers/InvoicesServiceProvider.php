<?php

namespace Modules\Invoices\Providers;

use Illuminate\Support\Facades\Gate;
use Modules\Invoices\Models\Invoice;
use Modules\Invoices\Policies\InvoicePolicy;
use Modules\Invoices\Repositories\InvoiceRepository;
use Modules\Invoices\Repositories\InvoiceRepositoryInterface;
use Nwidart\Modules\Support\ModuleServiceProvider;

class InvoicesServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Invoices';

    protected string $nameLower = 'invoices';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(InvoiceRepositoryInterface::class, InvoiceRepository::class);
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Invoice::class, InvoicePolicy::class);
    }
}
