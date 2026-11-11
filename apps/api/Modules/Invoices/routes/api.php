<?php

use Illuminate\Support\Facades\Route;
use Modules\Invoices\Http\Controllers\DirectPurchaseInvoiceController;
use Modules\Invoices\Http\Controllers\ProPurchaseInvoiceController;

Route::prefix('v1')
    ->name('invoices.')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        // One branch per invoice type: the URI owns the `type`, the controller
        // enforces it (direct purchase / pro purchase stay one model, one
        // service, one table).
        Route::apiResource('direct-purchase-invoices', DirectPurchaseInvoiceController::class)
            ->parameters(['direct-purchase-invoices' => 'invoice']);
        Route::apiResource('pro-purchase-invoices', ProPurchaseInvoiceController::class)
            ->parameters(['pro-purchase-invoices' => 'invoice']);

        Route::patch('direct-purchase-invoices/{invoice}/status', [DirectPurchaseInvoiceController::class, 'status'])->name('direct-purchase-invoices.status');
        Route::patch('pro-purchase-invoices/{invoice}/status', [ProPurchaseInvoiceController::class, 'status'])->name('pro-purchase-invoices.status');
    });
