<?php

use App\Platform\Announcements\Http\Controllers\AnnouncementsController;
use Illuminate\Support\Facades\Route;

// Announcements for the whole install (super admin; a provider may script these).
Route::get('/announcements', [AnnouncementsController::class, 'index']);
Route::post('/announcements', [AnnouncementsController::class, 'store']);
Route::put('/announcements/{announcement}', [AnnouncementsController::class, 'update']);
Route::patch('/announcements/{announcement}/visibility', [AnnouncementsController::class, 'visibility']);
Route::delete('/announcements/{announcement}', [AnnouncementsController::class, 'destroy']);
