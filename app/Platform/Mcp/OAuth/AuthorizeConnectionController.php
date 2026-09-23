<?php

namespace App\Platform\Mcp\OAuth;

use App\Domains\Accounts\Models\User;
use App\Platform\Mcp\Application\ConnectionService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Http\Controllers\AuthorizationController;
use Laravel\Passport\Scope;
use League\OAuth2\Server\AuthorizationServer;

/**
 * Passport's authorization endpoint, with one change: a client is only
 * approved without asking when the user already has a live connection for
 * it.
 *
 * Passport on its own skips the consent screen whenever the user holds an
 * active token for the client. That would skip the company picker too, so a
 * client whose connection was revoked, or whose company the user left, is
 * always shown the screen again. Requests for other scopes keep Passport's
 * behaviour.
 */
class AuthorizeConnectionController extends AuthorizationController
{
    public function __construct(
        AuthorizationServer $server,
        StatefulGuard $guard,
        ClientRepository $clients,
        private readonly ConnectionService $connections,
    ) {
        parent::__construct($server, $guard, $clients);
    }

    /**
     * @param  Scope[]  $scopes
     */
    protected function hasGrantedScopes(Authenticatable $user, Client $client, array $scopes): bool
    {
        if (! collect($scopes)->contains(fn (Scope $scope): bool => $scope->id === Registrar::OAUTH_SCOPE)) {
            return parent::hasGrantedScopes($user, $client, $scopes);
        }

        return $user instanceof User
            && $this->connections->liveFor($user, $client->getKey()) !== null
            && parent::hasGrantedScopes($user, $client, $scopes);
    }
}
