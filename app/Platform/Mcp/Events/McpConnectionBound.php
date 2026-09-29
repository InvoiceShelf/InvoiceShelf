<?php

namespace App\Platform\Mcp\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Someone connected an AI app to their account, or moved a connection to
 * another company.
 */
class McpConnectionBound
{
    use Dispatchable;

    public function __construct(
        public readonly int $connectionId,
        public readonly int $userId,
        public readonly int $companyId,
    ) {}
}
