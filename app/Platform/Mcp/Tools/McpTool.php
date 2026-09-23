<?php

namespace App\Platform\Mcp\Tools;

use App\Platform\Mcp\McpContext;
use Laravel\Mcp\Server\Tool;

/**
 * Base for every InvoiceShelf MCP tool.
 *
 * A tool is listed, and can be called, only when the connection and the
 * user allow it: a tool that writes is hidden from read-only connections,
 * and a tool that names a policy ability is hidden from users whose role in
 * the bound company lacks it. Tools never take a company from their
 * arguments; they read it from McpContext.
 *
 * Every tool declares all four MCP annotations explicitly, because clients
 * treat a tool without them as destructive and open-world.
 */
abstract class McpTool extends Tool
{
    /**
     * The policy ability checked before the tool is listed, as a pair of
     * ability and model class for Gate, or null when belonging to the company
     * is enough.
     *
     * @return array{0: string, 1: class-string}|null
     */
    protected function ability(): ?array
    {
        return null;
    }

    /**
     * Whether the tool changes data, which hides it from read-only
     * connections.
     */
    protected function writes(): bool
    {
        return false;
    }

    public function shouldRegister(McpContext $context): bool
    {
        if ($this->writes() && ! $context->canWrite()) {
            return false;
        }

        $ability = $this->ability();

        return $ability === null || $context->user->can($ability[0], $ability[1]);
    }
}
