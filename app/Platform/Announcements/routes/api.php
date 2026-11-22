<?php

use App\Platform\Announcements\Http\Controllers\DismissAnnouncementController;
use Illuminate\Support\Facades\Route;

Route::post('/announcements/{announcement}/dismiss', DismissAnnouncementController::class);
