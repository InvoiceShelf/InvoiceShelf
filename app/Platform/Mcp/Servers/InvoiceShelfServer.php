<?php

namespace App\Platform\Mcp\Servers;

use App\Platform\Mcp\Tools\Company\GetCompanyContextTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;

/**
 * The MCP server every connection talks to, at /mcp.
 *
 * Which tools a client sees depends on its connection (read-only or not) and
 * on the user's role in the bound company; see McpTool::shouldRegister().
 */
#[Name('InvoiceShelf')]
#[Instructions(<<<'TEXT'
    You are connected to one company in InvoiceShelf, an invoicing application,
    acting for the user who approved this connection. Every tool works in that
    company only; there is no way to choose another one. Start with
    get_company_context to learn the company's currency, tax setup and what this
    connection may do. Money is given and returned as decimal strings in major
    units (for example "120.00") together with a currency code. Tools that
    delete records or send email require confirm set to true: ask the user
    before calling them.
    TEXT)]
class InvoiceShelfServer extends Server
{
    /**
     * Every tool on one page, so clients that do not follow pagination still
     * see the whole list.
     */
    public int $defaultPaginationLength = 100;

    public int $maxPaginationLength = 100;

    protected array $tools = [
        GetCompanyContextTool::class,
    ];

    protected function boot(): void
    {
        $version = trim((string) @file_get_contents(base_path('version.md')));

        $this->version = $version !== '' ? $version : $this->version;
    }
}
