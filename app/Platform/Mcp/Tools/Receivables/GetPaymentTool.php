<?php

namespace App\Platform\Mcp\Tools\Receivables;

use App\Domains\Receivables\Models\Payment;
use App\Platform\Mcp\McpContext;
use App\Platform\Mcp\Presenters\PaymentPresenter;
use App\Platform\Mcp\Tools\McpTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_payment')]
#[Title('Get a payment')]
#[Description('One payment in full, by its number or id: amount, method, the invoices it settles and how much of it is still unapplied credit.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class GetPaymentTool extends McpTool
{
    protected function ability(McpContext $context): ?array
    {
        return ['viewAny', Payment::class];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'payment_number' => $schema->string()->description('The payment number, for example "PAY-000007".'),
            'payment_id' => $schema->integer()->description('The payment id, when the number is not known.'),
        ];
    }

    public function handle(Request $request, McpContext $context): Response|ResponseFactory
    {
        $request->validate([
            'payment_number' => ['required_without:payment_id', 'nullable', 'string'],
            'payment_id' => ['required_without:payment_number', 'nullable', 'integer'],
        ]);

        $payment = Payment::query()
            ->with(['customer', 'paymentMethod'])
            ->where('company_id', $context->company->id)
            ->when($request->get('payment_id'), fn ($query, $id) => $query->whereKey($id))
            ->when(! $request->get('payment_id'), fn ($query) => $query->where('payment_number', $request->get('payment_number')))
            ->first();

        if (! $payment) {
            return Response::error('There is no such payment in the company.');
        }

        $this->authorizeRecord($context, 'view', $payment);

        return Response::structured(PaymentPresenter::detail($payment));
    }
}
