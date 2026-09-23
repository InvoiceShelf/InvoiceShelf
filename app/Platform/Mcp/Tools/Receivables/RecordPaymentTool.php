<?php

namespace App\Platform\Mcp\Tools\Receivables;

use App\Domains\Receivables\Application\Composition\PaymentComposer;
use App\Domains\Receivables\Application\PaymentService;
use App\Domains\Receivables\Http\Requests\PaymentRequest;
use App\Domains\Receivables\Models\Payment;
use App\Platform\Mcp\McpContext;
use App\Platform\Mcp\Presenters\PaymentPresenter;
use App\Platform\Mcp\Support\DomainRequestValidator;
use App\Platform\Mcp\Tools\McpWriteTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('record_payment')]
#[Title('Record a payment')]
#[Description(<<<'TEXT'
    Record money received from a customer and apply it to their invoices.
    Name the invoices in allocations; an allocation without an amount takes
    what is still open on that invoice. Without an amount, the payment is the
    sum of the allocations, so "record the payment for INV-000012" needs only
    that invoice. Money left over stays with the customer as credit. Drafts and
    credit notes cannot be paid. Returns the payment and what it settled.
    TEXT)]
#[IsReadOnly(false)]
#[IsIdempotent(false)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class RecordPaymentTool extends McpWriteTool
{
    public function __construct(
        private readonly PaymentComposer $composer,
        private readonly DomainRequestValidator $validator,
        private readonly PaymentService $payments,
    ) {}

    protected function ability(McpContext $context): ?array
    {
        return ['create', Payment::class];
    }

    protected function creates(): bool
    {
        return true;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'customer_id' => $schema->integer()->description('Who paid; taken from the invoices when left out.'),
            'amount' => $schema->string()->description('The amount received in the customer\'s currency, a decimal like "250.00". The sum of the allocations when left out.'),
            'date' => $schema->string()->format('date')->description('Payment date, YYYY-MM-DD; today when left out.'),
            'payment_method' => $schema->string()->description('A payment method name or id from get_company_context.'),
            'number' => $schema->string()->description('Leave out to take the next payment number.'),
            'notes' => $schema->string(),
            'exchange_rate' => $schema->number()->description('For a customer in another currency: the value of one unit of their currency in the company\'s.'),
            'allocations' => $schema->array()->items($schema->object([
                'invoice_number' => $schema->string(),
                'invoice_id' => $schema->integer(),
                'amount' => $schema->string()->description('How much goes to this invoice; what is open on it when left out.'),
            ]))->description('The invoices this payment settles, in order.'),
            'custom_fields' => $schema->array()->items($schema->object(['slug' => $schema->string(), 'value' => $schema->string()])),
            ...$this->idempotencySchema($schema),
        ];
    }

    protected function write(Request $request, McpContext $context): array
    {
        $payload = $this->composer->payment(array_diff_key($request->all(), ['idempotency_key' => true]), $context->company->id);
        $validated = $this->validator->validate(PaymentRequest::class, $payload, $context);

        $payment = $this->payments->create(
            attributes: $validated->getPaymentPayload(),
            allocations: $validated->validated('allocations') ?? [],
            customFields: $validated->input('customFields') ?: null,
        );

        return PaymentPresenter::detail($payment->fresh(['customer', 'paymentMethod']));
    }
}
