<?php

namespace App\Platform\Mcp\Tools\Receivables;

use App\Domains\Accounts\Application\UserLocale;
use App\Domains\Receivables\Application\Composition\PaymentMailDefaults;
use App\Domains\Receivables\Application\PaymentService;
use App\Domains\Receivables\Http\Requests\SendPaymentRequest;
use App\Domains\Receivables\Models\Payment;
use App\Platform\Mcp\McpContext;
use App\Platform\Mcp\Support\DomainRequestValidator;
use App\Platform\Mcp\Tools\Concerns\RequiresConfirmation;
use App\Platform\Mcp\Tools\Concerns\SendsMail;
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

#[Name('send_payment_receipt')]
#[Title('Email a payment receipt')]
#[Description('Email a payment receipt to the customer with its PDF, the way the app\'s send dialog does. This reaches someone outside the app: ask the user first and set confirm to true.')]
#[IsReadOnly(false)]
#[IsIdempotent(false)]
#[IsDestructive(false)]
#[IsOpenWorld(true)]
class SendPaymentReceiptTool extends McpWriteTool
{
    use RequiresConfirmation;
    use SendsMail;

    public function __construct(
        private readonly PaymentMailDefaults $defaults,
        private readonly DomainRequestValidator $validator,
        private readonly PaymentService $payments,
    ) {}

    protected function ability(McpContext $context): ?array
    {
        return ['send-payment', Payment::class];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'payment_number' => $schema->string()->description('The payment number.'),
            'payment_id' => $schema->integer()->description('The payment id, when the number is not known.'),
            ...$this->mailSchema($schema),
            ...$this->confirmSchema($schema, 'the receipt is emailed to the customer'),
        ];
    }

    protected function write(Request $request, McpContext $context): array
    {
        $payment = Payment::query()
            ->where('company_id', $context->company->id)
            ->when($request->get('payment_id'), fn ($query, $id) => $query->whereKey($id), fn ($query) => $query->where('payment_number', $request->get('payment_number')))
            ->first() ?? $this->refuse('There is no such payment in the company.', 'payment_number');

        $this->authorizeRecord($context, 'send payment', $payment);

        if ($aborted = $this->unconfirmed($request, "Sending emails the receipt for {$payment->payment_number} to the customer")) {
            return $aborted;
        }

        $message = $this->message($request, $this->defaults->for($payment, UserLocale::for($context->user, $context->company->id)));
        $validated = $this->validator->validate(SendPaymentRequest::class, $message, $context, 'POST', ['payment' => $payment]);

        $this->spendSendAllowance($context);
        $this->payments->send($payment, $validated->all());

        return ['id' => $payment->id, 'number' => $payment->payment_number, 'sent_to' => $message['to']];
    }
}
