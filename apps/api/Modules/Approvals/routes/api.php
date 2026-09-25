<?php

use Illuminate\Support\Facades\Route;
use Modules\Approvals\Http\Controllers\ApprovalRequestController;

Route::prefix('v1/approvals')->name('approvals.')->middleware(['auth:sanctum'])->group(function () {
    Route::get('preview', [ApprovalRequestController::class, 'preview'])->name('preview');
    Route::get('inbox', [ApprovalRequestController::class, 'inbox'])->name('inbox');
    Route::get('inbox/count', [ApprovalRequestController::class, 'inboxCount'])->name('inbox.count');
    Route::get('outbox', [ApprovalRequestController::class, 'outbox'])->name('outbox');

    Route::apiResource('requests', ApprovalRequestController::class)->only(['index', 'store', 'show']);
    Route::post('requests/{approval}/actions', [ApprovalRequestController::class, 'actions'])
        ->name('requests.actions');
});
