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

#[Name('create_invoice')]
#[Title('Create an invoice')]
#[Description(<<<'TEXT'
    Create a draft invoice. Give the customer and the lines; the server works
    out the number, dates, template, currency and rate, catalogue prices and
    every discount, tax and total the way the invoice form does, so never
    compute amounts yourself. Call preview_document first to check the totals
    with the user. Returns the invoice as stored. Sending it is send_invoice.
    TEXT)]
#[IsReadOnly(false)]
#[IsIdempotent(false)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class CreateInvoiceTool extends McpWriteTool
{
    use DescribesDocuments;

    public function __construct(
        private readonly SalesDocumentComposer $composer,
        private readonly DomainRequestValidator $validator,
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
        return [...$this->documentSchema($schema, 'invoice', creating: true), ...$this->idempotencySchema($schema)];
    }

    protected function write(Request $request, McpContext $context): array
    {
        $payload = $this->composer->invoice($this->intent($request), $context->company->id, $context->user);
        $validated = $this->validator->validate(InvoicesRequest::class, $payload, $context);

        $invoice = $this->invoices->create(
            attributes: $validated->getInvoicePayload(),
            items: $validated->input('items'),
            taxes: $validated->input('taxes') ?: null,
            customFields: $validated->input('customFields') ?: null,
        );

        dispatch(new GenerateInvoicePdfJob($invoice));

        return DocumentPresenter::invoiceDetail($invoice);
    }
}
