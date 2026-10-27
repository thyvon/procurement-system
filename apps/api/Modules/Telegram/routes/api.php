<?php

use App\Http\Middleware\VerifyTelegramBotWebhook;
use App\Http\Middleware\VerifyTelegramWebhook;
use Illuminate\Support\Facades\Route;
use Modules\Telegram\Http\Controllers\Internal\BotWebhookController;
use Modules\Telegram\Http\Controllers\Internal\InboundTelegramController;
use Modules\Telegram\Http\Controllers\TelegramAccountController;
use Modules\Telegram\Http\Controllers\TelegramContactController;
use Modules\Telegram\Http\Controllers\TelegramLinkController;
use Modules\Telegram\Http\Controllers\TelegramMessageController;

// Internal webhook from the TDLib service — HMAC only, never public-auth.
Route::prefix('internal/telegram')
    ->name('telegram.internal.')
    ->middleware(VerifyTelegramWebhook::class)
    ->group(function () {
        Route::post('inbound', InboundTelegramController::class)->name('inbound');
    });

// Bot API webhook (setWebhook secret_token) — the /start deep-link flow.
Route::prefix('internal/telegram')
    ->name('telegram.internal.')
    ->middleware(VerifyTelegramBotWebhook::class)
    ->group(function () {
        Route::post('bot', BotWebhookController::class)->name('bot');
    });

Route::prefix('v1/telegram')
    ->name('telegram.')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::get('link', TelegramLinkController::class)->name('link');

        Route::post('accounts/{account}/login-code', [TelegramAccountController::class, 'loginCode'])
            ->name('accounts.login-code');
        Route::post('accounts/{account}/login-password', [TelegramAccountController::class, 'loginPassword'])
            ->name('accounts.login-password');
        Route::get('accounts/{account}/health', [TelegramAccountController::class, 'health'])
            ->name('accounts.health');

        Route::apiResource('accounts', TelegramAccountController::class)->names('accounts');
        Route::apiResource('contacts', TelegramContactController::class)->names('contacts');

        Route::get('messages', [TelegramMessageController::class, 'index'])->name('messages.index');
        Route::post('messages', [TelegramMessageController::class, 'store'])->name('messages.store');
    });
