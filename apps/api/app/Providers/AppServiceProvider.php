<?php

namespace App\Providers;

use App\Support\Health\StorageProbeCheck;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\RedisCheck;
use Spatie\Health\Facades\Health;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->validateConfig();
        $this->registerModelRules();
        $this->registerHealthChecks();
    }

    private function validateConfig(): void
    {
        $rules = [
            'app.key' => 'required|string|min:1',
            'database.connections.mysql.host' => 'required|string',
            'database.connections.mysql.database' => 'required|string',
        ];

        $validator = Validator::make(config()->all(), $rules);

        if ($validator->fails()) {
            throw new \RuntimeException('Invalid configuration: '.$validator->errors()->first());
        }
    }

    private function registerModelRules(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
    }

    private function registerHealthChecks(): void
    {
        Health::checks([
            DatabaseCheck::new(),
            StorageProbeCheck::new(),
            ...($this->cacheStoreIsRedis() ? [RedisCheck::new()] : []),
        ]);
    }

    private function cacheStoreIsRedis(): bool
    {
        return str_contains((string) config('cache.default'), 'redis');
    }
}
