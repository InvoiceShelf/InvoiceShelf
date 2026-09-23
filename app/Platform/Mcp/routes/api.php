<?php

use App\Platform\Mcp\Http\Controllers\ConnectionsController;
use Illuminate\Support\Facades\Route;

// The signed-in user's connected AI apps. A connection belongs to its user,
// not to the company in the header, so these sit outside the bouncer group.
Route::get('/mcp/server', [ConnectionsController::class, 'server']);
Route::get('/mcp/connections', [ConnectionsController::class, 'index']);
Route::patch('/mcp/connections/{connection}', [ConnectionsController::class, 'update']);
Route::delete('/mcp/connections/{connection}', [ConnectionsController::class, 'destroy']);
