<?php

use App\Platform\Notifications\Http\Controllers\InboxController;
use App\Platform\Notifications\Http\Controllers\NotificationPreferencesController;
use Illuminate\Support\Facades\Route;

// The caller's own notices and where they want them. Open to everyone signed
// in; the controllers only ever reach the caller's own rows.
Route::get('/notifications', [InboxController::class, 'index']);
Route::get('/notifications/unread-count', [InboxController::class, 'unreadCount']);
Route::post('/notifications/read-all', [InboxController::class, 'readAll']);
Route::post('/notifications/{notification}/read', [InboxController::class, 'read'])->whereUuid('notification');
Route::post('/notifications/{notification}/unread', [InboxController::class, 'unread'])->whereUuid('notification');
Route::delete('/notifications/{notification}', [InboxController::class, 'destroy'])->whereUuid('notification');

Route::get('/me/notification-preferences', [NotificationPreferencesController::class, 'show']);
Route::put('/me/notification-preferences', [NotificationPreferencesController::class, 'update']);
