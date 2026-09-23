<?php

namespace App\Platform\Mcp\Console;

use App\Domains\Accounts\Application\OAuth\OAuthKeyManager;
use App\Platform\Mcp\Application\McpSettings;
use Illuminate\Console\Command;

/**
 * Switch the MCP server on from the command line, creating the OAuth signing
 * keys when there are none.
 */
class EnableMcp extends Command
{
    protected $signature = 'mcp:enable';

    protected $description = 'Switch the MCP server on.';

    public function handle(McpSettings $settings, OAuthKeyManager $keys): int
    {
        if ($keys->ensure()) {
            $this->components->warn('OAuth signing keys were created in storage/. They must be readable by the user that runs the web server.');
        }

        $settings->setEnabled(true);

        $this->components->info('The MCP server is on at '.url('/mcp').'.');

        return self::SUCCESS;
    }
}
