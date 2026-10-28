<?php

/*
|--------------------------------------------------------------------------
| System Settings Whitelist
|--------------------------------------------------------------------------
|
| Single source of truth for overridable system settings. Each group maps
| an API key to its runtime config path, validation type, bounds, and the
| environment variable it falls back to. The database stores ONLY values
| keyed by this whitelist — a row whose group/key pair is not defined here
| is ignored by the config overlay, so database content can never point
| the overlay at an arbitrary config path.
|
| Secret keys are write-only: their value is never serialized in API
| responses and never rendered in the UI (only a `configured` flag),
| whether the effective value comes from the database or the environment.
|
*/

return [
    'telegram' => [
        'label' => 'Telegram integration',
        'keys' => [
            'bot_token' => [
                'label' => 'Bot token',
                'config' => 'services.telegram.bot_token',
                'env' => 'TELEGRAM_BOT_TOKEN',
                'type' => 'string',
                'secret' => true,
                'max' => 512,
            ],
            'bot_username' => [
                'label' => 'Bot username',
                'config' => 'services.telegram.bot_username',
                'env' => 'TELEGRAM_BOT_USERNAME',
                'type' => 'string',
                'secret' => false,
                'max' => 64,
            ],
            'bot_webhook_secret' => [
                'label' => 'Bot webhook secret',
                'config' => 'services.telegram.bot_webhook_secret',
                'env' => 'TELEGRAM_BOT_WEBHOOK_SECRET',
                'type' => 'string',
                'secret' => true,
                'max' => 256,
            ],
            'alerts_enabled' => [
                'label' => 'Approval alerts enabled',
                'config' => 'services.telegram.alerts_enabled',
                'env' => 'TELEGRAM_ALERTS_ENABLED',
                'type' => 'boolean',
                'secret' => false,
            ],
            'alert_rate_per_minute' => [
                'label' => 'Alerts per minute',
                'config' => 'services.telegram.alert_rate_per_minute',
                'env' => 'TELEGRAM_ALERTS_RATE_PER_MINUTE',
                'type' => 'integer',
                'secret' => false,
                'min' => 1,
                'max' => 600,
            ],
            'link_code_ttl_minutes' => [
                'label' => 'Deep-link code lifetime (minutes)',
                'config' => 'services.telegram.link_code_ttl_minutes',
                'env' => 'TELEGRAM_LINK_CODE_TTL_MINUTES',
                'type' => 'integer',
                'secret' => false,
                'min' => 1,
                'max' => 1440,
            ],
            'base_url' => [
                'label' => 'TDLib base URL',
                'config' => 'services.telegram.base_url',
                'env' => 'TELEGRAM_TDLIB_URL',
                'type' => 'url',
                'secret' => false,
                'max' => 512,
            ],
            'service_token' => [
                'label' => 'TDLib service token',
                'config' => 'services.telegram.service_token',
                'env' => 'TELEGRAM_SERVICE_TOKEN',
                'type' => 'string',
                'secret' => true,
                'max' => 512,
            ],
            'webhook_secret' => [
                'label' => 'TDLib webhook secret',
                'config' => 'services.telegram.webhook_secret',
                'env' => 'TELEGRAM_WEBHOOK_SECRET',
                'type' => 'string',
                'secret' => true,
                'max' => 512,
            ],
            'rate_per_minute' => [
                'label' => 'Messages per minute (per account)',
                'config' => 'services.telegram.rate_per_minute',
                'env' => 'TELEGRAM_RATE_PER_MINUTE',
                'type' => 'integer',
                'secret' => false,
                'min' => 1,
                'max' => 6000,
            ],
            'jitter_max_ms' => [
                'label' => 'Send jitter ceiling (ms)',
                'config' => 'services.telegram.jitter_max_ms',
                'env' => 'TELEGRAM_JITTER_MAX_MS',
                'type' => 'integer',
                'secret' => false,
                'min' => 0,
                'max' => 60000,
            ],
        ],
    ],
];
