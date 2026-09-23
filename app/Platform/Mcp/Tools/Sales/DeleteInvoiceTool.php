<?php

namespace App\Platform\Mcp\Tools\Sales;

use App\Domains\Sales\Application\InvoiceService;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Mcp\McpContext;
use App\Platform\Mcp\Tools\Concerns\RequiresConfirmation;
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

#[Name('delete_invoice')]
#[Title('Delete an invoice')]
#[Description('Delete an invoice or a credit note for good. An invoice with payments against it cannot be deleted; delete or move the payments first. Ask the user first and set confirm to true.')]
#[IsReadOnly(false)]
#[IsIdempotent(true)]
#[IsDestructive(true)]
#[IsOpenWorld(false)]
class DeleteInvoiceTool extends McpWriteTool
{
    use DescribesDocuments;
    use RequiresConfirmation;

    public function __construct(
        private readonly InvoiceService $invoices,
    ) {}

    protected function ability(McpContext $context): ?array
    {
        return ['delete-invoice', Invoice::class];
    }

    public function schema(JsonSchema $schema): array
    {
        return [...$this->documentReference($schema, 'invoice'), ...$this->confirmSchema($schema, 'the invoice is deleted for good')];
    }

    protected function write(Request $request, McpContext $context): array
    {
        $invoice = $this->findInvoice($request, $context);
        $this->authorizeRecord($context, 'delete', $invoice);

        if ($aborted = $this->unconfirmed($request, "Deleting removes invoice {$invoice->invoice_number} for good")) {
            return $aborted;
        }

        if ($invoice->allocations()->exists()) {
            $this->refuse("Invoice {$invoice->invoice_number} has payments against it. Delete or move those payments first.");
        }

        $this->invoices->delete(collect([$invoice->id]));

        return ['id' => $invoice->id, 'number' => $invoice->invoice_number, 'deleted' => true];
    }
}
