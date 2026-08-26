<?php

namespace Modules\Auth\Providers;

use Modules\Auth\Contracts\RevokesUserTokens;
use Modules\Auth\Services\AuthService;
use Nwidart\Modules\Support\ModuleServiceProvider;

class AuthServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Auth';

    protected string $nameLower = 'auth';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(RevokesUserTokens::class, AuthService::class);
    }
}
