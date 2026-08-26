<?php

use Illuminate\Support\Facades\Route;
use Modules\Products\Http\Controllers\BrandController;
use Modules\Products\Http\Controllers\ProductCategoryController;
use Modules\Products\Http\Controllers\ProductController;
use Modules\Products\Http\Controllers\ProductGroupController;
use Modules\Products\Http\Controllers\ProductRefController;
use Modules\Products\Http\Controllers\UomController;
use Modules\Products\Http\Controllers\VariationController;

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

        Route::get('variation-templates', [VariationController::class, 'templateIndex'])->name('variation-templates.index');
        Route::post('variation-templates', [VariationController::class, 'templateStore'])->name('variation-templates.store');
        Route::post('merge-variation', [VariationController::class, 'merge'])->name('merge-variation');

        // /items must not shadow /refs, /categories etc. — register last.
        Route::apiResource('items', ProductController::class)
            ->parameters(['items' => 'product'])
            ->names('items');
    });
