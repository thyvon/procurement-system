<?php

use Illuminate\Support\Facades\Route;
use Modules\EmployeeProfiles\Http\Controllers\EmployeeProfileController;

Route::prefix('v1/users/{user}')
    ->name('users.')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::get('employee-profile', [EmployeeProfileController::class, 'show'])->name('employee-profile.show');
        Route::get('employee-profiles', [EmployeeProfileController::class, 'index'])->name('employee-profiles.index');
        Route::post('employee-profiles', [EmployeeProfileController::class, 'store'])->name('employee-profiles.store');
    });
