<?php

use Illuminate\Support\Facades\Route;
use Modules\Organization\Http\Controllers\EntityController;

Route::prefix('v1/entities')
    ->name('entities.')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::apiResource('/', EntityController::class)
            ->parameters(['' => 'entity'])
            ->names('entities');
    });
