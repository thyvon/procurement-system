<?php

use Illuminate\Support\Facades\Route;
use Modules\Invoices\Http\Controllers\InvoiceController;

Route::prefix('v1/invoices')
    ->name('invoices.')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::apiResource('/', InvoiceController::class)
            ->parameters(['' => 'invoice'])
            ->names('invoices');
        Route::patch('/{invoice}/status', [InvoiceController::class, 'status'])->name('status');
    });
