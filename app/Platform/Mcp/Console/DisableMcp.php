<?php

namespace App\Platform\Mcp\Console;

use App\Platform\Mcp\Application\McpSettings;
use Illuminate\Console\Command;

/**
 * Switch the MCP server off. Connections are kept, and work again when the
 * server is switched back on.
 */
class DisableMcp extends Command
{
    protected $signature = 'mcp:disable';

    protected $description = 'Switch the MCP server off.';

    public function handle(McpSettings $settings): int
    {
        $settings->setEnabled(false);

        $this->components->info('The MCP server is off.');

        return self::SUCCESS;
    }
}
