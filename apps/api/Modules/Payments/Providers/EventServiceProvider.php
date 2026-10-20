<?php

namespace Modules\Payments\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Approvals\Events\ApprovalStatusChanged;
use Modules\Payments\Listeners\SyncPaymentApprovalStatus;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        ApprovalStatusChanged::class => [
            SyncPaymentApprovalStatus::class,
        ],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = true;

    protected function configureEmailVerification(): void {}
}
