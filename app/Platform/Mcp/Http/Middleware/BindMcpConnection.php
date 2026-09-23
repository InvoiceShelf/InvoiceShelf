<?php

namespace App\Platform\Mcp\Http\Middleware;

use App\Domains\Accounts\Application\AccessRevoker;
use App\Domains\Accounts\Models\User;
use App\Platform\Mcp\Application\ConnectionService;
use App\Platform\Mcp\McpContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\AccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pins an MCP request to the company its connection was granted for.
 *
 * Runs after `auth:oauth` and before the host's `company` and `bouncer`
 * middleware. The token must carry the MCP scope and belong to a connection
 * whose user still belongs to its company; otherwise the grant is revoked and
 * the client is told to authorize again. The `company` header is then
 * overwritten with the bound company, so whatever the client sent is ignored
 * and everything downstream (the company middleware, Bouncer's scope, the
 * models that read the header) sees the right one.
 */
class BindMcpConnection
{
    public function __construct(
        private readonly ConnectionService $connections,
        private readonly AccessRevoker $revoker,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (! $user instanceof User || ! $token instanceof AccessToken || ! $token->can(Registrar::OAUTH_SCOPE)) {
            return $this->unauthorized();
        }

        $clientId = (string) $token->oauth_client_id;
        $connection = $this->connections->liveFor($user, $clientId);

        if ($connection === null) {
            $this->revoker->revokeClient($user->id, $clientId);

            return $this->unauthorized();
        }

        $request->headers->set('company', (string) $connection->company_id);

        app()->instance(McpContext::class, new McpContext($connection, $user, $connection->company));

        $this->connections->touch($connection);

        return $next($request);
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json([
            'error' => 'invalid_token',
            'error_description' => 'This connection is no longer valid. Connect the app again.',
        ], 401);
    }
}
