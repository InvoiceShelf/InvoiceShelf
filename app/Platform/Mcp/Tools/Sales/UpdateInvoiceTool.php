<?php

namespace App\Platform\Mcp\Tools\Sales;

use App\Domains\Sales\Application\Composition\SalesDocumentComposer;
use App\Domains\Sales\Application\InvoiceService;
use App\Domains\Sales\Http\Requests\InvoicesRequest;
use App\Domains\Sales\Jobs\GenerateInvoicePdfJob;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Mcp\McpContext;
use App\Platform\Mcp\Presenters\DocumentPresenter;
use App\Platform\Mcp\Support\DomainRequestValidator;
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

#[Name('update_invoice')]
#[Title('Change an invoice')]
#[Description(<<<'TEXT'
    Change an invoice. Only what is given changes; lines, when given, replace
    all the current ones. The totals are worked out again, and a tax already on
    the invoice keeps the rate it was applied at. A paid invoice cannot move to
    another customer or drop below what was paid. Credit notes cannot be
    changed.
    TEXT)]
#[IsReadOnly(false)]
#[IsIdempotent(true)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class UpdateInvoiceTool extends McpWriteTool
{
    use DescribesDocuments;

    public function __construct(
        private readonly SalesDocumentComposer $composer,
        private readonly DomainRequestValidator $validator,
        private readonly InvoiceService $invoices,
    ) {}

    protected function ability(McpContext $context): ?array
    {
        return ['edit-invoice', Invoice::class];
    }

    public function schema(JsonSchema $schema): array
    {
        return [...$this->documentReference($schema, 'invoice'), ...$this->documentSchema($schema, 'invoice', creating: false)];
    }

    protected function write(Request $request, McpContext $context): array
    {
        $invoice = $this->findInvoice($request, $context);

        if ($invoice->isCreditNote()) {
            $this->refuse("{$invoice->invoice_number} is a credit note, which cannot be changed.");
        }

        if (! $invoice->allow_edit) {
            $this->refuse($invoice->hasCreditNotes()
                ? "{$invoice->invoice_number} has credit notes against it, so it can no longer be changed."
                : "The company does not allow changing {$invoice->invoice_number} now that it has been paid (the retrospective edits setting).");
        }

        $this->authorizeRecord($context, 'update', $invoice);

        $payload = $this->composer->invoice($this->intent($request), $context->company->id, $context->user, $invoice);
        $validated = $this->validator->validate(InvoicesRequest::class, $payload, $context, 'PUT', ['invoice' => $invoice]);

        $invoice = $this->invoices->update(
            invoice: $invoice,
            attributes: $validated->getInvoicePayload(),
            items: $validated->input('items'),
            taxes: $validated->input('taxes') ?: null,
            customFields: $validated->input('customFields') ?: null,
        );

        dispatch(new GenerateInvoicePdfJob($invoice, true));

        return DocumentPresenter::invoiceDetail($invoice);
    }
}
