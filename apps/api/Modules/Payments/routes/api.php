<?php

use Illuminate\Support\Facades\Route;
use Modules\Payments\Http\Controllers\AdvanceLiquidationController;
use Modules\Payments\Http\Controllers\CashClearanceController;
use Modules\Payments\Http\Controllers\CashRequestController;

Route::prefix('v1/payments')
    ->name('payments.')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::apiResource('cash-requests', CashRequestController::class)->names('cash-requests');
        Route::apiResource('cash-clearances', CashClearanceController::class)->names('cash-clearances');
        Route::apiResource('advance-liquidations', AdvanceLiquidationController::class)->names('advance-liquidations');
        Route::patch('cash-requests/{cash_request}/status', [CashRequestController::class, 'status'])->name('cash-requests.status');
        Route::patch('cash-clearances/{cash_clearance}/status', [CashClearanceController::class, 'status'])->name('cash-clearances.status');
        Route::patch('advance-liquidations/{advance_liquidation}/status', [AdvanceLiquidationController::class, 'status'])->name('advance-liquidations.status');
    });
