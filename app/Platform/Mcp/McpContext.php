<?php

namespace App\Platform\Mcp;

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use App\Platform\Mcp\Models\McpConnection;

/**
 * Who is calling the MCP server, for which company, and with what access.
 *
 * Bound into the container for the length of one MCP request by
 * BindMcpConnection, after the connection has been checked. Tools read it;
 * they never take a company from their arguments.
 */
final class McpContext
{
    public function __construct(
        public readonly McpConnection $connection,
        public readonly User $user,
        public readonly Company $company,
    ) {}

    public function canWrite(): bool
    {
        return $this->connection->canWrite();
    }
}
