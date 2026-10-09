<?php

use Illuminate\Support\Facades\Route;
use Modules\EPurchase\Http\Controllers\EPurchaseItemController;
use Modules\EPurchase\Http\Controllers\EPurchasePrController;
use Modules\EPurchase\Http\Controllers\EPurchaseSupplierController;

Route::prefix('v1/epurchase')
    ->name('epurchase.')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::get('items', [EPurchaseItemController::class, 'index'])->name('items.index');
        Route::get('suppliers', [EPurchaseSupplierController::class, 'index'])->name('suppliers.index');
        Route::get('prs', [EPurchasePrController::class, 'index'])->name('prs.index');
        Route::get('prs/{prId}', [EPurchasePrController::class, 'show'])
            ->whereNumber('prId')
            ->name('prs.show');
    });
