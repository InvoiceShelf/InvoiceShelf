<?php

use App\Platform\Mcp\Http\Middleware\BindMcpConnection;
use App\Platform\Mcp\Servers\InvoiceShelfServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

// The MCP endpoint and the OAuth discovery and registration it needs. None of
// it carries a middleware group: no session and no CSRF, a bearer token only.
// Everything answers 404 until a super administrator switches MCP on.
Route::middleware('mcp.enabled')->group(function () {
    Route::middleware('throttle:mcp-discovery')->group(fn () => Mcp::oauthRoutes());

    Mcp::web('/mcp', InvoiceShelfServer::class)->middleware([
        'throttle:mcp-ip',
        'auth:oauth',
        BindMcpConnection::class,
        'company',
        'bouncer',
        'throttle:mcp',
    ]);
});
