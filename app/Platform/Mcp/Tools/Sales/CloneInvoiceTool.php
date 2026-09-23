<?php

namespace App\Platform\Mcp\Tools\Sales;

use App\Domains\Sales\Application\InvoiceService;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Mcp\McpContext;
use App\Platform\Mcp\Presenters\DocumentPresenter;
use App\Platform\Mcp\Tools\McpWriteTool;
use App\Platform\Mcp\Tools\Sales\Concerns\DescribesDocuments;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('clone_invoice')]
#[Title('Copy an invoice')]
#[Description('Copy an invoice into a new draft with the next number and today\'s date, the same customer, lines and taxes. Use update_invoice on the copy to change it.')]
#[IsReadOnly(false)]
#[IsIdempotent(false)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class CloneInvoiceTool extends McpWriteTool
{
    use DescribesDocuments;

    public function __construct(
        private readonly InvoiceService $invoices,
    ) {}

    protected function ability(McpContext $context): ?array
    {
        return ['create', Invoice::class];
    }

    protected function creates(): bool
    {
        return true;
    }

    public function schema(JsonSchema $schema): array
    {
        return [...$this->documentReference($schema, 'invoice'), ...$this->idempotencySchema($schema)];
    }

    protected function write(Request $request, McpContext $context): array
    {
        $invoice = $this->findInvoice($request, $context);

        if ($invoice->isCreditNote()) {
            $this->refuse("{$invoice->invoice_number} is a credit note, which cannot be copied.");
        }

        $this->authorizeRecord($context, 'view', $invoice);

        return DocumentPresenter::invoiceDetail($this->invoices->clone($invoice));
    }
}
