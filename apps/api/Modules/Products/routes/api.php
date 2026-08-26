<?php

use Illuminate\Support\Facades\Route;
use Modules\Products\Http\Controllers\BrandController;
use Modules\Products\Http\Controllers\ProductCategoryController;
use Modules\Products\Http\Controllers\ProductController;
use Modules\Products\Http\Controllers\ProductGroupController;
use Modules\Products\Http\Controllers\ProductRefController;
use Modules\Products\Http\Controllers\UomController;

Route::prefix('v1/products')
    ->name('products.')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::get('refs', [ProductRefController::class, 'index'])->name('refs');

        Route::get('categories/tree', [ProductCategoryController::class, 'tree'])->name('categories.tree');
        Route::apiResource('categories', ProductCategoryController::class)->names('categories');
        Route::apiResource('brands', BrandController::class)->names('brands');
        Route::apiResource('groups', ProductGroupController::class)->names('groups');

        Route::apiResource('uoms', UomController::class)->names('uoms');

        // /items must not shadow /refs, /categories etc. — register last.
        Route::apiResource('items', ProductController::class)
            ->parameters(['items' => 'product'])
            ->names('items');
    });
