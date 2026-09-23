<?php

namespace App\Platform\Mcp\Http\Middleware;

use App\Domains\Accounts\Application\OAuth\OAuthKeyManager;
use App\Platform\Mcp\Application\McpSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The `mcp.enabled` alias: the MCP endpoint and its discovery documents
 * answer 404 until a super administrator switches the server on, and 503
 * while the OAuth signing keys are missing (a token could not be checked).
 */
class EnsureMcpEnabled
{
    public function __construct(
        private readonly McpSettings $settings,
        private readonly OAuthKeyManager $keys,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->settings->enabled(), 404);

        if (! $this->keys->ready()) {
            return response()->json([
                'error' => 'temporarily_unavailable',
                'error_description' => 'The MCP server is switched on but its OAuth signing keys are missing. An administrator must create them.',
            ], 503);
        }

        return $next($request);
    }
}
