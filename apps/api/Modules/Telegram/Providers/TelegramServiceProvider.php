<?php

namespace Modules\Telegram\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Telegram\Models\TelegramAccount;
use Modules\Telegram\Models\TelegramContact;
use Modules\Telegram\Models\TelegramMessage;
use Modules\Telegram\Policies\TelegramAccountPolicy;
use Modules\Telegram\Policies\TelegramContactPolicy;
use Modules\Telegram\Policies\TelegramMessagePolicy;
use Modules\Telegram\Repositories\TelegramAccountRepository;
use Modules\Telegram\Repositories\TelegramAccountRepositoryInterface;
use Modules\Telegram\Repositories\TelegramContactRepository;
use Modules\Telegram\Repositories\TelegramContactRepositoryInterface;
use Modules\Telegram\Repositories\TelegramMessageRepository;
use Modules\Telegram\Repositories\TelegramMessageRepositoryInterface;
use Nwidart\Modules\Support\ModuleServiceProvider;

class TelegramServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Telegram';

    protected string $nameLower = 'telegram';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(TelegramAccountRepositoryInterface::class, TelegramAccountRepository::class);
        $this->app->bind(TelegramContactRepositoryInterface::class, TelegramContactRepository::class);
        $this->app->bind(TelegramMessageRepositoryInterface::class, TelegramMessageRepository::class);
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(TelegramAccount::class, TelegramAccountPolicy::class);
        Gate::policy(TelegramContact::class, TelegramContactPolicy::class);
        Gate::policy(TelegramMessage::class, TelegramMessagePolicy::class);

        RateLimiter::for('telegram-account', function (object $job) {
            return Limit::perMinute((int) config('services.telegram.rate_per_minute', 5))
                ->by($job->accountId ?? 'unknown');
        });
    }
}
