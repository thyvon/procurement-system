<?php

use Illuminate\Support\Facades\Route;
use Modules\Notifications\Http\Controllers\NotificationController;

Route::prefix('v1/notifications')->name('notifications.')->middleware(['auth:sanctum'])->group(function () {
    Route::get('unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
    Route::post('read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::post('{notification}/read', [NotificationController::class, 'markRead'])->name('read');
});
