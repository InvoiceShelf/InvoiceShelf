<?php

namespace App\Platform\Mcp\Application;

use App\Domains\Accounts\Application\AccessRevoker;
use App\Domains\Accounts\Events\CompanyAccessRevoked;
use App\Domains\Accounts\Events\UserAccessRevoked;
use App\Domains\Accounts\Models\User;
use App\Platform\Mcp\Models\McpConnection;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Passport\Client;

/**
 * Everything that creates, checks and ends an MCP connection.
 */
class ConnectionService
{
    public function __construct(
        private readonly AccessRevoker $revoker,
    ) {}

    /**
     * Record the company and access level a user just consented to for a
     * client.
     *
     * Consenting again from the same client replaces the row. When the
     * company changes, everything the client held for the old company is
     * revoked first, so an earlier token can never act in the new one. Call
     * this before the new authorization code is issued, since the revocation
     * also covers codes.
     */
    public function bind(User $user, Client $client, int $companyId, string $access): McpConnection
    {
        $existing = McpConnection::query()
            ->where('user_id', $user->id)
            ->where('oauth_client_id', $client->getKey())
            ->first();

        if ($existing !== null && $existing->company_id !== $companyId) {
            $this->revoker->revokeClient($user->id, $client->getKey());
        }

        return McpConnection::query()->updateOrCreate(
            ['user_id' => $user->id, 'oauth_client_id' => $client->getKey()],
            [
                'company_id' => $companyId,
                'access' => $access,
                'client_name' => $client->name,
                'redirect_host' => $this->redirectHost($client),
            ],
        );
    }

    /**
     * The connection a token acts through, provided the user still belongs to
     * its company. Null otherwise.
     */
    public function liveFor(User $user, string $clientId): ?McpConnection
    {
        $connection = McpConnection::query()
            ->where('user_id', $user->id)
            ->where('oauth_client_id', $clientId)
            ->first();

        if ($connection === null || ! $user->hasCompany($connection->company_id)) {
            return null;
        }

        return $connection;
    }

    /**
     * @return Collection<int, McpConnection>
     */
    public function forUser(User $user): Collection
    {
        return McpConnection::query()
            ->with('company:id,name')
            ->where('user_id', $user->id)
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * End a connection: its tokens are revoked and the row removed.
     */
    public function revoke(McpConnection $connection): void
    {
        $this->revoker->revokeClient($connection->user_id, $connection->oauth_client_id);

        $connection->delete();
    }

    /**
     * Take write access away. Going the other way needs a new consent.
     */
    public function downgrade(McpConnection $connection): McpConnection
    {
        $connection->update(['access' => McpConnection::ACCESS_READ]);

        return $connection;
    }

    /**
     * Record use, at most once a minute so a busy client does not write on
     * every call.
     */
    public function touch(McpConnection $connection): void
    {
        if ($connection->last_used_at === null || $connection->last_used_at->lt(now()->subMinute())) {
            $connection->forceFill(['last_used_at' => now()])->saveQuietly();
        }
    }

    /**
     * The user left a company, or it was deleted: end their connections to it.
     */
    public function handleCompanyAccessRevoked(CompanyAccessRevoked $event): void
    {
        McpConnection::query()
            ->where('user_id', $event->userId)
            ->where('company_id', $event->companyId)
            ->get()
            ->each(fn (McpConnection $connection) => $this->revoke($connection));
    }

    /**
     * The account is going away; its tokens are already revoked.
     */
    public function handleUserAccessRevoked(UserAccessRevoked $event): void
    {
        McpConnection::query()->where('user_id', $event->userId)->delete();
    }

    /**
     * The host a client redirects to, shown on the consent screen and in the
     * connection list because a client's name is whatever it registered.
     */
    public function redirectHost(Client $client): ?string
    {
        $uri = (string) ($client->redirect_uris[0] ?? '');

        return parse_url($uri, PHP_URL_HOST) ?: (parse_url($uri, PHP_URL_SCHEME) ?: null);
    }
}
