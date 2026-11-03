<?php

namespace Modules\Telegram\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Approvals\Events\ApprovalActed;
use Modules\Telegram\Events\TelegramMessageReceived;
use Modules\Telegram\Listeners\UpdateTelegramAlertsOnDecision;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        // Both transports (web app, Telegram buttons) decide through
        // ApprovalService — rewrite the delivered alerts to match.
        ApprovalActed::class => [
            UpdateTelegramAlertsOnDecision::class,
        ],
        // Future modules (RFQ/PO confirmations, buyer notifications) subscribe
        // to TelegramMessageReceived here — Phase 1 has no consumers yet.
        TelegramMessageReceived::class => [],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = true;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
