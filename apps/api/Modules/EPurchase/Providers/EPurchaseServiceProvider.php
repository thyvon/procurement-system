<?php

namespace Modules\EPurchase\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class EPurchaseServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'EPurchase';

    protected string $nameLower = 'epurchase';

    protected array $providers = [
        RouteServiceProvider::class,
    ];
}
