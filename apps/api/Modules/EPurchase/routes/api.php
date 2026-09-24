<?php

use Illuminate\Support\Facades\Route;
use Modules\EPurchase\Http\Controllers\EPurchaseItemController;
use Modules\EPurchase\Http\Controllers\EPurchaseSupplierController;
use Modules\EPurchase\Http\Controllers\EPurchaseVendorController;

Route::prefix('v1/epurchase')
    ->name('epurchase.')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::get('items', [EPurchaseItemController::class, 'index'])->name('items.index');
        Route::get('suppliers', [EPurchaseSupplierController::class, 'index'])->name('suppliers.index');
        Route::get('vendor-search', [EPurchaseVendorController::class, 'search'])->name('vendors.search');
        Route::post('vendor-info', [EPurchaseVendorController::class, 'info'])->name('vendors.info');
    });
