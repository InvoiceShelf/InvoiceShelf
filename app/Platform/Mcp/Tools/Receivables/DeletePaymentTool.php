<?php

namespace App\Platform\Mcp\Tools\Receivables;

use App\Domains\Receivables\Application\PaymentService;
use App\Domains\Receivables\Models\Payment;
use App\Platform\Mcp\McpContext;
use App\Platform\Mcp\Tools\Concerns\RequiresConfirmation;
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

#[Name('delete_payment')]
#[Title('Delete a payment')]
#[Description('Delete a payment for good. The invoices it settled owe that money again. Ask the user first and set confirm to true.')]
#[IsReadOnly(false)]
#[IsIdempotent(true)]
#[IsDestructive(true)]
#[IsOpenWorld(false)]
class DeletePaymentTool extends McpWriteTool
{
    use RequiresConfirmation;

    public function __construct(
        private readonly PaymentService $payments,
    ) {}

    protected function ability(McpContext $context): ?array
    {
        return ['delete-payment', Payment::class];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'payment_number' => $schema->string()->description('The payment number.'),
            'payment_id' => $schema->integer()->description('The payment id, when the number is not known.'),
            ...$this->confirmSchema($schema, 'the payment is deleted for good and its invoices owe the money again'),
        ];
    }

    protected function write(Request $request, McpContext $context): array
    {
        $payment = Payment::query()
            ->where('company_id', $context->company->id)
            ->when($request->get('payment_id'), fn ($query, $id) => $query->whereKey($id), fn ($query) => $query->where('payment_number', $request->get('payment_number')))
            ->first() ?? $this->refuse('There is no such payment in the company.', 'payment_number');

        $this->authorizeRecord($context, 'delete', $payment);

        if ($aborted = $this->unconfirmed($request, "Deleting removes payment {$payment->payment_number} for good and its invoices owe that money again")) {
            return $aborted;
        }

        $this->payments->delete(collect([$payment->id]));

        return ['id' => $payment->id, 'number' => $payment->payment_number, 'deleted' => true];
    }
}
