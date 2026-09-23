<?php

namespace App\Platform\Mcp\Tools\Receivables;

use App\Domains\Receivables\Models\Payment;
use App\Platform\Mcp\McpContext;
use App\Platform\Mcp\Presenters\PaymentPresenter;
use App\Platform\Mcp\Queries\Window;
use App\Platform\Mcp\Tools\Concerns\Paginates;
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

#[Name('list_recent_payments')]
#[Title('List recent payments')]
#[Description('Payments received, newest first, optionally for one customer or between two dates. Returns numbers, dates, customers, amounts and payment methods.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class ListRecentPaymentsTool extends McpTool
{
    use Paginates;

    protected function ability(McpContext $context): ?array
    {
        return ['viewAny', Payment::class];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'customer_id' => $schema->integer()->description('Only this customer\'s payments.'),
            'from_date' => $schema->string()->format('date')->description('Payment date from, YYYY-MM-DD.'),
            'to_date' => $schema->string()->format('date')->description('Payment date to, YYYY-MM-DD, included.'),
            ...$this->pageSchema($schema),
        ];
    }

    public function handle(Request $request, McpContext $context): ResponseFactory
    {
        $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $query = Payment::query()
            ->with(['customer', 'paymentMethod'])
            ->where('company_id', $context->company->id)
            ->when($request->get('customer_id'), fn ($query, $id) => $query->where('customer_id', $id))
            ->orderByDesc('payment_date')
            ->orderByDesc('id');

        Window::apply($query, 'payment_date', $request->get('from_date'), $request->get('to_date'));

        $page = $this->page($query, $request, fn (Payment $payment) => PaymentPresenter::summary($payment));

        return Response::structured(['payments' => $page['rows'], 'page' => $page['page'], 'has_more' => $page['has_more']]);
    }
}
