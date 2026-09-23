<?php

use Illuminate\Support\Facades\Route;
use Modules\Users\Http\Controllers\UserController;

Route::prefix('v1/users')
    ->name('users.')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::apiResource('/', UserController::class)
            ->parameters(['' => 'user'])
            ->names('users');
        Route::post('/{user}/avatar', [UserController::class, 'updateAvatar'])->name('avatar');
    });
