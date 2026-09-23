<?php

namespace Modules\Auth\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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

    public function boot(): void
    {
        parent::boot();

        RateLimiter::for('company-login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
