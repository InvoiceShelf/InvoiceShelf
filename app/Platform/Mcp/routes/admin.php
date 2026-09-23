<?php

use App\Platform\Mcp\Http\Controllers\Admin\McpSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/mcp', [McpSettingsController::class, 'show']);
Route::put('/mcp', [McpSettingsController::class, 'update']);
Route::post('/mcp/keys', [McpSettingsController::class, 'regenerateKeys']);
