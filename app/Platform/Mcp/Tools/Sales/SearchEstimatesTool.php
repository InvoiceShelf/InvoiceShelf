<?php

namespace App\Platform\Mcp\Tools\Sales;

use App\Domains\Sales\Models\Estimate;
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

#[Name('search_estimates')]
#[Title('Search estimates')]
#[Description('Find estimates (quotes) by number, reference or customer name, status, customer and date. Newest first. Returns numbers, customers, dates, expiry dates, totals and statuses.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class SearchEstimatesTool extends McpTool
{
    use Paginates;

    public const STATUSES = ['DRAFT', 'SENT', 'VIEWED', 'EXPIRED', 'ACCEPTED', 'REJECTED'];

    protected function ability(McpContext $context): ?array
    {
        return ['viewAny', Estimate::class];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Text to look for in the estimate number, the reference number or the customer name.'),
            'status' => $schema->string()->enum(self::STATUSES),
            'customer_id' => $schema->integer()->description('Only this customer\'s estimates.'),
            'from_date' => $schema->string()->format('date')->description('Estimate date from, YYYY-MM-DD.'),
            'to_date' => $schema->string()->format('date')->description('Estimate date to, YYYY-MM-DD, included.'),
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

        $query = Estimate::query()
            ->with('customer')
            ->where('company_id', $context->company->id)
            ->when($request->get('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->get('customer_id'), fn ($query, $id) => $query->where('customer_id', $id))
            ->orderByDesc('estimate_date')
            ->orderByDesc('id');

        Window::apply($query, 'estimate_date', $request->get('from_date'), $request->get('to_date'));

        if ($text = trim((string) $request->get('query'))) {
            $needle = '%'.$text.'%';
            $query->where(fn ($where) => $where
                ->where('estimate_number', 'LIKE', $needle)
                ->orWhere('reference_number', 'LIKE', $needle)
                ->orWhereHas('customer', fn ($customer) => $customer
                    ->where('name', 'LIKE', $needle)
                    ->orWhere('company_name', 'LIKE', $needle)
                    ->orWhere('contact_name', 'LIKE', $needle)));
        }

        $page = $this->page($query, $request, fn (Estimate $estimate) => DocumentPresenter::estimateSummary($estimate));

        return Response::structured(['estimates' => $page['rows'], 'page' => $page['page'], 'has_more' => $page['has_more']]);
    }
}
