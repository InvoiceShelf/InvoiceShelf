<?php

namespace App\Platform\Mcp\Tools\Sales;

use App\Domains\Sales\Models\Invoice;
use App\Platform\Mcp\McpContext;
use App\Platform\Mcp\Presenters\DocumentPresenter;
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

#[Name('search_invoices')]
#[Title('Search invoices')]
#[Description('Find invoices by number, reference or customer name, status, customer and date. Newest first. Credit notes are left out unless include_credit_notes is true. Returns numbers, customers, dates, totals, what is still due and statuses.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class SearchInvoicesTool extends McpTool
{
    use Paginates;

    public const STATUSES = ['DRAFT', 'SENT', 'VIEWED', 'COMPLETED', 'UNPAID', 'PARTIALLY_PAID', 'PAID', 'OVERDUE'];

    protected function ability(McpContext $context): ?array
    {
        return ['viewAny', Invoice::class];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Text to look for in the invoice number, the reference number or the customer name.'),
            'status' => $schema->string()->enum(self::STATUSES)->description('DRAFT, SENT, VIEWED and COMPLETED are the sending status; UNPAID, PARTIALLY_PAID and PAID the payment status; OVERDUE is issued, unpaid and past its due date.'),
            'customer_id' => $schema->integer()->description('Only this customer\'s invoices.'),
            'from_date' => $schema->string()->format('date')->description('Invoice date from, YYYY-MM-DD.'),
            'to_date' => $schema->string()->format('date')->description('Invoice date to, YYYY-MM-DD, included.'),
            'include_credit_notes' => $schema->boolean()->description('Also list credit notes.'),
            ...$this->pageSchema($schema),
        ];
    }

    public function handle(Request $request, McpContext $context): ResponseFactory
    {
        $request->validate([
            'status' => ['nullable', 'in:'.implode(',', self::STATUSES)],
            'customer_id' => ['nullable', 'integer'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $query = Invoice::query()
            ->with('customer')
            ->where('company_id', $context->company->id)
            ->when(! $request->get('include_credit_notes'), fn ($query) => $query->where('type', Invoice::TYPE_INVOICE))
            ->when($request->get('customer_id'), fn ($query, $id) => $query->where('customer_id', $id))
            ->orderByDesc('invoice_date')
            ->orderByDesc('id');

        $this->filterStatus($query, $request->get('status'), $context);
        Window::apply($query, 'invoice_date', $request->get('from_date'), $request->get('to_date'));

        if ($text = trim((string) $request->get('query'))) {
            $needle = '%'.$text.'%';
            $query->where(fn ($where) => $where
                ->where('invoice_number', 'LIKE', $needle)
                ->orWhere('reference_number', 'LIKE', $needle)
                ->orWhereHas('customer', fn ($customer) => $customer
                    ->where('name', 'LIKE', $needle)
                    ->orWhere('company_name', 'LIKE', $needle)
                    ->orWhere('contact_name', 'LIKE', $needle)));
        }

        $page = $this->page($query, $request, fn (Invoice $invoice) => DocumentPresenter::invoiceSummary($invoice));

        return Response::structured(['invoices' => $page['rows'], 'page' => $page['page'], 'has_more' => $page['has_more']]);
    }

    private function filterStatus($query, ?string $status, McpContext $context): void
    {
        match ($status) {
            null, '' => null,
            'UNPAID', 'PARTIALLY_PAID', 'PAID' => $query->where('paid_status', $status),
            'OVERDUE' => OverdueInvoices::constrain($query, $context->company->id),
            default => $query->where('status', $status),
        };
    }
}
