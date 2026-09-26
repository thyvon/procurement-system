<?php

use Illuminate\Support\Facades\Route;
use Modules\Approvals\Http\Controllers\ApprovalFlowController;
use Modules\Approvals\Http\Controllers\ApprovalRequestController;
use Modules\Approvals\Http\Controllers\ApprovalSettingController;
use Modules\Approvals\Http\Controllers\TocaEntryController;

Route::prefix('v1/approvals')->name('approvals.')->middleware(['auth:sanctum'])->group(function () {
    Route::get('preview', [ApprovalRequestController::class, 'preview'])->name('preview');
    Route::put('drafts', [ApprovalRequestController::class, 'draft'])->name('drafts.store');
    Route::get('inbox', [ApprovalRequestController::class, 'inbox'])->name('inbox');
    Route::get('inbox/count', [ApprovalRequestController::class, 'inboxCount'])->name('inbox.count');
    Route::get('outbox', [ApprovalRequestController::class, 'outbox'])->name('outbox');

    Route::apiResource('requests', ApprovalRequestController::class)->only(['index', 'store', 'show']);
    Route::post('requests/{approval}/actions', [ApprovalRequestController::class, 'actions'])
        ->name('requests.actions');

    Route::apiResource('settings', ApprovalSettingController::class)->only(['index']);
    Route::apiResource('flows', ApprovalFlowController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::apiResource('toca-entries', TocaEntryController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy'])
        ->parameters(['toca-entries' => 'entry']);
});
