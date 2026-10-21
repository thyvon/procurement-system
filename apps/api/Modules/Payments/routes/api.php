<?php

use Illuminate\Support\Facades\Route;
use Modules\Payments\Http\Controllers\AdvanceLiquidationController;
use Modules\Payments\Http\Controllers\AdvanceRequestController;
use Modules\Payments\Http\Controllers\CashClearanceController;
use Modules\Payments\Http\Controllers\CashRequestController;
use Modules\Payments\Http\Controllers\PettyCashRequestController;

Route::prefix('v1/payments')
    ->name('payments.')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        // One branch per request type: the URI owns the `type`, the controller
        // enforces it (PC / AD / CR stay one model, one service, one table).
        Route::apiResource('petty-cash-requests', PettyCashRequestController::class)
            ->parameters(['petty-cash-requests' => 'cash_request']);
        Route::apiResource('advance-requests', AdvanceRequestController::class)
            ->parameters(['advance-requests' => 'cash_request']);
        Route::apiResource('cash-requests', CashRequestController::class)
            ->parameters(['cash-requests' => 'cash_request']);

        Route::apiResource('cash-clearances', CashClearanceController::class);
        Route::apiResource('advance-liquidations', AdvanceLiquidationController::class);

        Route::patch('petty-cash-requests/{cash_request}/status', [PettyCashRequestController::class, 'status'])->name('petty-cash-requests.status');
        Route::patch('advance-requests/{cash_request}/status', [AdvanceRequestController::class, 'status'])->name('advance-requests.status');
        Route::patch('cash-requests/{cash_request}/status', [CashRequestController::class, 'status'])->name('cash-requests.status');
        Route::patch('cash-clearances/{cash_clearance}/status', [CashClearanceController::class, 'status'])->name('cash-clearances.status');
        Route::patch('advance-liquidations/{advance_liquidation}/status', [AdvanceLiquidationController::class, 'status'])->name('advance-liquidations.status');

        Route::get('petty-cash-requests/{cash_request}/print', [PettyCashRequestController::class, 'print'])->name('petty-cash-requests.print');
        Route::get('advance-requests/{cash_request}/print', [AdvanceRequestController::class, 'print'])->name('advance-requests.print');
        Route::get('cash-requests/{cash_request}/print', [CashRequestController::class, 'print'])->name('cash-requests.print');
        Route::get('cash-clearances/{cash_clearance}/print', [CashClearanceController::class, 'print'])->name('cash-clearances.print');
        Route::get('advance-liquidations/{advance_liquidation}/print', [AdvanceLiquidationController::class, 'print'])->name('advance-liquidations.print');
    });
