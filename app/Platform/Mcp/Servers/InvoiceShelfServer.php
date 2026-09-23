<?php

namespace App\Platform\Mcp\Servers;

use App\Platform\Mcp\Tools\Catalog\CreateItemTool;
use App\Platform\Mcp\Tools\Catalog\SearchItemsTool;
use App\Platform\Mcp\Tools\Catalog\UpdateItemTool;
use App\Platform\Mcp\Tools\Company\GetCompanyContextTool;
use App\Platform\Mcp\Tools\Contacts\CreateCustomerTool;
use App\Platform\Mcp\Tools\Contacts\GetCustomerTool;
use App\Platform\Mcp\Tools\Contacts\SearchCustomersTool;
use App\Platform\Mcp\Tools\Contacts\UpdateCustomerTool;
use App\Platform\Mcp\Tools\Purchases\CreateExpenseTool;
use App\Platform\Mcp\Tools\Purchases\DeleteExpenseTool;
use App\Platform\Mcp\Tools\Purchases\ListExpenseCategoriesTool;
use App\Platform\Mcp\Tools\Purchases\SearchExpensesTool;
use App\Platform\Mcp\Tools\Receivables\DeletePaymentTool;
use App\Platform\Mcp\Tools\Receivables\GetPaymentTool;
use App\Platform\Mcp\Tools\Receivables\ListRecentPaymentsTool;
use App\Platform\Mcp\Tools\Receivables\RecordPaymentTool;
use App\Platform\Mcp\Tools\Receivables\SendPaymentReceiptTool;
use App\Platform\Mcp\Tools\Reporting\GetCompanyStatsTool;
use App\Platform\Mcp\Tools\Reporting\RankExpenseCategoriesTool;
use App\Platform\Mcp\Tools\Reporting\RankTopCustomersTool;
use App\Platform\Mcp\Tools\Reporting\RankTopItemsTool;
use App\Platform\Mcp\Tools\Sales\ChangeEstimateStatusTool;
use App\Platform\Mcp\Tools\Sales\ChangeInvoiceStatusTool;
use App\Platform\Mcp\Tools\Sales\CloneInvoiceTool;
use App\Platform\Mcp\Tools\Sales\ConvertEstimateToInvoiceTool;
use App\Platform\Mcp\Tools\Sales\CreateEstimateTool;
use App\Platform\Mcp\Tools\Sales\CreateInvoiceTool;
use App\Platform\Mcp\Tools\Sales\DeleteEstimateTool;
use App\Platform\Mcp\Tools\Sales\DeleteInvoiceTool;
use App\Platform\Mcp\Tools\Sales\GetEstimateTool;
use App\Platform\Mcp\Tools\Sales\GetInvoiceTool;
use App\Platform\Mcp\Tools\Sales\ListOverdueInvoicesTool;
use App\Platform\Mcp\Tools\Sales\PreviewDocumentTool;
use App\Platform\Mcp\Tools\Sales\SearchEstimatesTool;
use App\Platform\Mcp\Tools\Sales\SearchInvoicesTool;
use App\Platform\Mcp\Tools\Sales\SendEstimateTool;
use App\Platform\Mcp\Tools\Sales\SendInvoiceTool;
use App\Platform\Mcp\Tools\Sales\UpdateEstimateTool;
use App\Platform\Mcp\Tools\Sales\UpdateInvoiceTool;
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
        SearchCustomersTool::class,
        GetCustomerTool::class,
        SearchItemsTool::class,
        SearchInvoicesTool::class,
        GetInvoiceTool::class,
        ListOverdueInvoicesTool::class,
        SearchEstimatesTool::class,
        GetEstimateTool::class,
        ListRecentPaymentsTool::class,
        GetPaymentTool::class,
        SearchExpensesTool::class,
        ListExpenseCategoriesTool::class,
        GetCompanyStatsTool::class,
        RankTopCustomersTool::class,
        RankTopItemsTool::class,
        RankExpenseCategoriesTool::class,
        CreateCustomerTool::class,
        UpdateCustomerTool::class,
        CreateItemTool::class,
        UpdateItemTool::class,
        PreviewDocumentTool::class,
        CreateInvoiceTool::class,
        UpdateInvoiceTool::class,
        ChangeInvoiceStatusTool::class,
        CloneInvoiceTool::class,
        CreateEstimateTool::class,
        UpdateEstimateTool::class,
        ChangeEstimateStatusTool::class,
        ConvertEstimateToInvoiceTool::class,
        RecordPaymentTool::class,
        CreateExpenseTool::class,
        SendInvoiceTool::class,
        SendEstimateTool::class,
        SendPaymentReceiptTool::class,
        DeleteInvoiceTool::class,
        DeleteEstimateTool::class,
        DeletePaymentTool::class,
        DeleteExpenseTool::class,
    ];

    protected function boot(): void
    {
        $version = trim((string) @file_get_contents(base_path('version.md')));

        $this->version = $version !== '' ? $version : $this->version;
    }
}
