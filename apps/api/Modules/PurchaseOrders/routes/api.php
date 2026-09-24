<?php

use Illuminate\Support\Facades\Route;
use Modules\PurchaseOrders\Http\Controllers\EvaluationController;

Route::prefix('v1/purchase-orders')
    ->name('purchase-orders.')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::apiResource('evaluations', EvaluationController::class);
    });
