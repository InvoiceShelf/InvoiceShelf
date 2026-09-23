<?php

namespace App\Domains\Accounts\Application;

use App\Domains\Accounts\Events\CompanyAccessRevoked;
use App\Domains\Accounts\Events\UserAccessRevoked;
use Illuminate\Support\Collection;
use Laravel\Passport\Passport;

/**
 * The one place that ends access granted through the OAuth server.
 *
 * Tokens are revoked rather than deleted, so an audit can still see what was
 * issued; Passport rejects a revoked token on the next request because it
 * checks the stored row every time. Consumers that keep their own records
 * about a grant (the MCP server binds each one to a company) listen for the
 * events fired here instead of being called directly, which keeps this domain
 * unaware of them.
 */
class AccessRevoker
{
    /**
     * End everything a user has granted: every client, every company. Used
     * when the account is deleted.
     */
    public function revokeUser(int $userId): void
    {
        $this->revokeTokens($userId);

        UserAccessRevoked::dispatch($userId);
    }

    /**
     * End one user's grant to one client.
     */
    public function revokeClient(int $userId, string $clientId): void
    {
        $this->revokeTokens($userId, $clientId);
    }

    /**
     * Tell consumers that a user no longer belongs to a company.
     *
     * OAuth tokens are not tied to a company, so nothing is revoked here;
     * a consumer that bound a grant to this company revokes that grant from
     * its listener.
     */
    public function revokeCompany(int $userId, int $companyId): void
    {
        CompanyAccessRevoked::dispatch($userId, $companyId);
    }

    /**
     * End every grant on the installation, as after the signing keys were
     * replaced and no issued token can be verified any more.
     */
    public function revokeEverything(): void
    {
        Passport::token()->newQuery()->where('revoked', false)->update(['revoked' => true]);
        Passport::refreshToken()->newQuery()->where('revoked', false)->update(['revoked' => true]);
        Passport::authCode()->newQuery()->where('revoked', false)->update(['revoked' => true]);
    }

    /**
     * Revoke a user's access tokens, the refresh tokens hanging off them, and
     * any authorization code not yet exchanged, optionally for one client.
     */
    private function revokeTokens(int $userId, ?string $clientId = null): void
    {
        /** @var Collection<int, string> $tokenIds */
        $tokenIds = Passport::token()->newQuery()
            ->where('user_id', $userId)
            ->when($clientId, fn ($query) => $query->where('client_id', $clientId))
            ->pluck('id');

        $tokenIds->chunk(500)->each(function (Collection $ids) {
            Passport::token()->newQuery()->whereIn('id', $ids->all())->update(['revoked' => true]);
            Passport::refreshToken()->newQuery()->whereIn('access_token_id', $ids->all())->update(['revoked' => true]);
        });

        Passport::authCode()->newQuery()
            ->where('user_id', $userId)
            ->when($clientId, fn ($query) => $query->where('client_id', $clientId))
            ->update(['revoked' => true]);
    }
}
