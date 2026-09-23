<?php

namespace App\Platform\Mcp\OAuth;

use App\Platform\Mcp\Application\ConnectionService;
use App\Platform\Mcp\Models\McpConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Bridge\Client as BridgeClient;
use Laravel\Passport\Bridge\Scope;
use Laravel\Passport\Bridge\User as BridgeUser;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Passport's approval endpoint, extended with what the consent screen asks:
 * the company the client will work in and whether it may write.
 *
 * Only requests for the MCP scope are bound; any other consumer of the OAuth
 * server gets Passport's own approval. The choice is validated before the
 * pending request is taken from the session, so a bad submission sends the
 * user back to the screen instead of ending the flow. The connection is
 * written before the authorization code is issued, in one transaction,
 * because re-binding to another company revokes the client's earlier codes
 * and tokens.
 */
class ApproveConnectionController extends ApproveAuthorizationController
{
    public function __construct(
        AuthorizationServer $server,
        private readonly ClientRepository $clients,
        private readonly ConnectionService $connections,
    ) {
        parent::__construct($server);
    }

    public function approve(Request $request, ResponseInterface $psrResponse): Response
    {
        if (! $this->requestsMcp($request)) {
            return parent::approve($request, $psrResponse);
        }

        $choice = $request->validate([
            'company_id' => ['required', 'integer'],
            'access' => ['required', Rule::in([McpConnection::ACCESS_READ, McpConnection::ACCESS_WRITE])],
        ]);

        $user = $request->user();

        abort_unless($user->hasCompany((int) $choice['company_id']), 403);

        $authRequest = $this->getAuthRequestFromSession($request);
        $client = $this->clients->find($authRequest->getClient()->getIdentifier());

        abort_if($client === null, 403);

        return DB::transaction(function () use ($user, $client, $choice, $authRequest, $psrResponse) {
            $this->connections->bind($user, $client, (int) $choice['company_id'], $choice['access']);

            $authRequest->setAuthorizationApproved(true);

            return $this->withErrorHandling(fn () => $this->convertResponse(
                $this->server->completeAuthorizationRequest($authRequest, $psrResponse)
            ));
        });
    }

    /**
     * Whether the pending request asks for the MCP scope. Read without
     * removing it from the session, which the approval does later.
     */
    private function requestsMcp(Request $request): bool
    {
        $pending = $request->session()->get('authRequest');

        if (! is_string($pending)) {
            return false;
        }

        $authRequest = unserialize($pending, ['allowed_classes' => [
            AuthorizationRequest::class,
            BridgeClient::class,
            Scope::class,
            BridgeUser::class,
        ]]);

        return $authRequest instanceof AuthorizationRequest && collect($authRequest->getScopes())
            ->contains(fn (Scope $scope): bool => $scope->getIdentifier() === Registrar::OAUTH_SCOPE);
    }
}
