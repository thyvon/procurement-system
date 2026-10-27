<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'telegram' => [
        // TDLib service (internal HTTP, bearer-authenticated).
        'base_url' => env('TELEGRAM_TDLIB_URL'),
        'service_token' => env('TELEGRAM_SERVICE_TOKEN'),
        // Shared secret the TDLib service signs inbound webhooks with.
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        // Per-account outbound ceiling (jobs/minute), enforced by queue middleware.
        'rate_per_minute' => env('TELEGRAM_RATE_PER_MINUTE', 5),
        // Random pre-send delay ceiling in ms (0 disables — tests).
        'jitter_max_ms' => env('TELEGRAM_JITTER_MAX_MS', 3000),
        // Bot API (api.telegram.org) — approval alerts to users' direct chats.
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        // Kill switch for approval alerts; database notifications always fire.
        'alerts_enabled' => env('TELEGRAM_ALERTS_ENABLED', true),
        // Outbound alert ceiling (jobs/minute), enforced by queue middleware.
        'alert_rate_per_minute' => env('TELEGRAM_ALERTS_RATE_PER_MINUTE', 30),
        // Bot public username for the /start deep link (https://t.me/<username>).
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        // secret_token Telegram echoes back in X-Telegram-Bot-Api-Secret-Token
        // on the bot webhook (VerifyTelegramBotWebhook).
        'bot_webhook_secret' => env('TELEGRAM_BOT_WEBHOOK_SECRET'),
        // Lifetime of a /start deep-link code, in minutes.
        'link_code_ttl_minutes' => env('TELEGRAM_LINK_CODE_TTL_MINUTES', 10),
    ],

];
