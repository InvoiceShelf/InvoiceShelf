<?php

namespace App\Platform\Mcp\Application;

use App\Platform\Operations\Models\Setting;
use Throwable;

/**
 * Installation-wide switches for the MCP server, kept as global settings.
 */
class McpSettings
{
    public const ENABLED = 'mcp_enabled';

    public const REDIRECT_DOMAINS = 'mcp_redirect_domains';

    /**
     * Whether a super administrator switched the server on. Off by default,
     * and off while the settings table is unreachable (before installation).
     */
    public function enabled(): bool
    {
        try {
            return Setting::getSetting(self::ENABLED) === 'YES';
        } catch (Throwable) {
            return false;
        }
    }

    public function setEnabled(bool $enabled): void
    {
        Setting::setSetting(self::ENABLED, $enabled ? 'YES' : 'NO');
    }

    /**
     * Redirect origins an administrator allowed on top of config/mcp.php.
     *
     * @return list<string>
     */
    public function extraRedirectDomains(): array
    {
        $stored = json_decode((string) Setting::getSetting(self::REDIRECT_DOMAINS), true);

        return is_array($stored) ? array_values(array_filter($stored, 'is_string')) : [];
    }

    /**
     * @param  list<string>  $domains
     */
    public function setExtraRedirectDomains(array $domains): void
    {
        Setting::setSetting(self::REDIRECT_DOMAINS, json_encode(array_values($domains)));
    }
}
