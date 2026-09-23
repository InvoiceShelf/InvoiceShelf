<?php

namespace App\Domains\Accounts\Application\OAuth;

use Closure;

/**
 * The installation's OAuth authorization server, and who is using it.
 *
 * The server itself (Passport, its keys, its tables and the `oauth` guard)
 * belongs to the accounts domain, but it has no purpose of its own: a feature
 * that hands out tokens (the MCP server first, perhaps the mobile app later)
 * registers itself here as a consumer, with a check for whether it is switched
 * on. The server answers only while at least one consumer is, so an
 * installation that uses none of them exposes no OAuth endpoints at all.
 */
class OAuthServer
{
    /**
     * @var array<string, Closure(): bool>
     */
    private array $consumers = [];

    /**
     * Register a feature that issues tokens through this server.
     *
     * @param  Closure(): bool  $enabled  whether the feature is currently switched on
     */
    public function consumer(string $name, Closure $enabled): void
    {
        $this->consumers[$name] = $enabled;
    }

    /**
     * Whether any registered consumer is switched on.
     */
    public function enabled(): bool
    {
        foreach ($this->consumers as $enabled) {
            if ($enabled()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Names of the registered consumers, in registration order.
     *
     * @return list<string>
     */
    public function consumers(): array
    {
        return array_keys($this->consumers);
    }
}
