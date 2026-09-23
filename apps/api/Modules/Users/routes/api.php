<?php

use Illuminate\Support\Facades\Route;
use Modules\Users\Http\Controllers\PermissionController;
use Modules\Users\Http\Controllers\RoleController;
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

Route::prefix('v1/roles')
    ->name('roles.')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::apiResource('/', RoleController::class)
            ->parameters(['' => 'role'])
            ->names('roles');
    });

Route::prefix('v1/permissions')
    ->name('permissions.')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::get('/', [PermissionController::class, 'index'])->name('index');
    });
