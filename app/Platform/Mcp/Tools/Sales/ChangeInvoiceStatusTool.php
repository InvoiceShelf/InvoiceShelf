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

#[Name('change_invoice_status')]
#[Title('Mark an invoice sent or completed')]
#[Description(<<<'TEXT'
    Mark an invoice as SENT (for one delivered outside the app; nothing is
    emailed) or as COMPLETED (only once it is fully paid or credited).
    TEXT)]
#[IsReadOnly(false)]
#[IsIdempotent(true)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class ChangeInvoiceStatusTool extends McpWriteTool
{
    use DescribesDocuments;

    public function __construct(
        private readonly InvoiceService $invoices,
    ) {}

    protected function ability(McpContext $context): ?array
    {
        return ['send-invoice', Invoice::class];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->documentReference($schema, 'invoice'),
            'status' => $schema->string()->enum([Invoice::STATUS_SENT, Invoice::STATUS_COMPLETED])->required(),
        ];
    }

    protected function write(Request $request, McpContext $context): array
    {
        $request->validate(['status' => ['required', 'in:'.Invoice::STATUS_SENT.','.Invoice::STATUS_COMPLETED]]);

        $invoice = $this->findInvoice($request, $context);
        $this->authorizeRecord($context, 'send invoice', $invoice);

        if ($invoice->isCreditNote()) {
            $this->refuse("{$invoice->invoice_number} is a credit note; its status follows the invoice it credits.");
        }

        $this->invoices->changeStatus($invoice, $request->get('status'));

        return DocumentPresenter::invoiceSummary($invoice->fresh());
    }
}
