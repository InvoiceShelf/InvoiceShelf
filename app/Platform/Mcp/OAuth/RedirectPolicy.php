<?php

namespace App\Platform\Mcp\OAuth;

use App\Platform\Mcp\Application\McpSettings;

/**
 * Which redirect origins an AI client may register.
 *
 * The defaults in config/mcp.php cover claude.ai, ChatGPT and loopback
 * callbacks; an administrator may add origins for other clients. Neither list
 * may contain a wildcard.
 */
class RedirectPolicy
{
    public function __construct(
        private readonly McpSettings $settings,
    ) {}

    /**
     * @return list<string>
     */
    public function defaults(): array
    {
        return array_values(array_filter(
            (array) config('mcp.redirect_domains', []),
            fn ($domain) => is_string($domain) && self::isValidOrigin($domain),
        ));
    }

    /**
     * @return list<string>
     */
    public function domains(): array
    {
        $extra = array_filter($this->settings->extraRedirectDomains(), self::isValidOrigin(...));

        return array_values(array_unique([...$this->defaults(), ...$extra]));
    }

    /**
     * A bare origin: http or https, a host, an optional port, nothing else.
     * Plain http is accepted only for loopback hosts.
     */
    public static function isValidOrigin(string $origin): bool
    {
        if (str_contains($origin, '*')) {
            return false;
        }

        $parts = parse_url($origin);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])
            || isset($parts['path']) || isset($parts['query']) || isset($parts['fragment']) || isset($parts['user'])) {
            return false;
        }

        if ($parts['scheme'] === 'https') {
            return true;
        }

        return $parts['scheme'] === 'http' && in_array($parts['host'], ['localhost', '127.0.0.1', '[::1]'], true);
    }
}
