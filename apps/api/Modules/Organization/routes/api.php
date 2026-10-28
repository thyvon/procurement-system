<?php

use Illuminate\Support\Facades\Route;
use Modules\Organization\Http\Controllers\BrandingController;
use Modules\Organization\Http\Controllers\CampusController;
use Modules\Organization\Http\Controllers\DepartmentController;
use Modules\Organization\Http\Controllers\DivisionController;
use Modules\Organization\Http\Controllers\EntityController;
use Modules\Organization\Http\Controllers\OrganizationImportController;
use Modules\Organization\Http\Controllers\SystemSettingController;

Route::get('v1/branding', BrandingController::class)->name('branding');

Route::prefix('v1/system-settings')
    ->name('system-settings.')
    ->middleware(['auth:sanctum'])
    ->whereIn('group', array_keys((array) config('system-settings', [])))
    ->group(function () {
        Route::get('{group}', [SystemSettingController::class, 'index'])->name('index');
        Route::put('{group}', [SystemSettingController::class, 'update'])->name('update');
    });

Route::prefix('v1/entities')
    ->name('entities.')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::apiResource('/', EntityController::class)
            ->parameters(['' => 'entity'])
            ->names('entities');
        Route::post('/{entity}/logo', [EntityController::class, 'updateLogo'])->name('logo');
    });

Route::prefix('v1/organization')
    ->name('organization.')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::get('campuses/import/template', [OrganizationImportController::class, 'campusTemplate'])
            ->name('campuses.import.template');
        Route::post('campuses/import', [OrganizationImportController::class, 'storeCampuses'])
            ->name('campuses.import.store');
        Route::get('departments/import/template', [OrganizationImportController::class, 'departmentTemplate'])
            ->name('departments.import.template');
        Route::post('departments/import', [OrganizationImportController::class, 'storeDepartments'])
            ->name('departments.import.store');

        Route::apiResource('campuses', CampusController::class)->names('campuses');
        Route::apiResource('divisions', DivisionController::class)->names('divisions');
        Route::apiResource('departments', DepartmentController::class)->names('departments');
    });
