<?php

namespace App\Platform\Mcp\Policies;

use App\Domains\Accounts\Models\User;
use App\Platform\Mcp\Models\McpConnection;

/**
 * A connection is managed by the user who made it, and by nobody else.
 */
class McpConnectionPolicy
{
    public function update(User $user, McpConnection $connection): bool
    {
        return $connection->user_id === $user->id;
    }

    public function delete(User $user, McpConnection $connection): bool
    {
        return $connection->user_id === $user->id;
    }
}
