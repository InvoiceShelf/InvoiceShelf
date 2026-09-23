<?php

namespace App\Platform\Mcp\Tools\Sales;

use App\Domains\Accounts\Application\UserLocale;
use App\Domains\Sales\Application\Composition\InvoiceMailDefaults;
use App\Domains\Sales\Application\InvoiceService;
use App\Domains\Sales\Http\Requests\SendInvoiceRequest;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Mcp\McpContext;
use App\Platform\Mcp\Support\DomainRequestValidator;
use App\Platform\Mcp\Tools\Concerns\RequiresConfirmation;
use App\Platform\Mcp\Tools\Concerns\SendsMail;
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

#[Name('send_invoice')]
#[Title('Email an invoice')]
#[Description(<<<'TEXT'
    Email an invoice (or a credit note) to the customer with its PDF, the way
    the app's send dialog does, and mark a draft as sent. The recipient,
    subject and message default to what the dialog would prefill. This reaches
    someone outside the app: ask the user first and set confirm to true.
    TEXT)]
#[IsReadOnly(false)]
#[IsIdempotent(false)]
#[IsDestructive(false)]
#[IsOpenWorld(true)]
class SendInvoiceTool extends McpWriteTool
{
    use DescribesDocuments;
    use RequiresConfirmation;
    use SendsMail;

    public function __construct(
        private readonly InvoiceMailDefaults $defaults,
        private readonly DomainRequestValidator $validator,
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
            ...$this->mailSchema($schema),
            ...$this->confirmSchema($schema, 'the invoice is emailed to the customer'),
        ];
    }

    protected function write(Request $request, McpContext $context): array
    {
        $invoice = $this->findInvoice($request, $context);
        $this->authorizeRecord($context, 'send invoice', $invoice);

        if ($aborted = $this->unconfirmed($request, "Sending emails invoice {$invoice->invoice_number} to the customer")) {
            return $aborted;
        }

        $message = $this->message($request, $this->defaults->for($invoice, UserLocale::for($context->user, $context->company->id)));
        $validated = $this->validator->validate(SendInvoiceRequest::class, $message, $context, 'POST', ['invoice' => $invoice]);

        $this->spendSendAllowance($context);
        $this->invoices->send($invoice, $validated->all());

        return ['id' => $invoice->id, 'number' => $invoice->invoice_number, 'sent_to' => $message['to'], 'status' => $invoice->fresh()->status];
    }
}
