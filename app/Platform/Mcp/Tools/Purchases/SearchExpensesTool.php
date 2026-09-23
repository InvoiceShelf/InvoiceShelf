<?php

namespace App\Platform\Mcp\Tools\Purchases;

use App\Domains\Purchases\Models\Expense;
use App\Platform\Mcp\McpContext;
use App\Platform\Mcp\Presenters\ExpensePresenter;
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

#[Name('search_expenses')]
#[Title('Search expenses')]
#[Description('Find expenses by category, customer, date or words in their notes. Newest first. Returns dates, categories, amounts, payment methods and whether a receipt is attached.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class SearchExpensesTool extends McpTool
{
    use Paginates;

    protected function ability(McpContext $context): ?array
    {
        return ['viewAny', Expense::class];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Text to look for in the notes or the expense number.'),
            'category_id' => $schema->integer()->description('Only this category, from list_expense_categories.'),
            'customer_id' => $schema->integer()->description('Only expenses billed to this customer.'),
            'from_date' => $schema->string()->format('date')->description('Expense date from, YYYY-MM-DD.'),
            'to_date' => $schema->string()->format('date')->description('Expense date to, YYYY-MM-DD, included.'),
            ...$this->pageSchema($schema),
        ];
    }

    public function handle(Request $request, McpContext $context): ResponseFactory
    {
        $request->validate([
            'category_id' => ['nullable', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $query = Expense::query()
            ->with(['category', 'customer', 'paymentMethod', 'media'])
            ->where('company_id', $context->company->id)
            ->when($request->get('category_id'), fn ($query, $id) => $query->where('expense_category_id', $id))
            ->when($request->get('customer_id'), fn ($query, $id) => $query->where('customer_id', $id))
            ->orderByDesc('expense_date')
            ->orderByDesc('id');

        Window::apply($query, 'expense_date', $request->get('from_date'), $request->get('to_date'));

        if ($text = trim((string) $request->get('query'))) {
            $needle = '%'.$text.'%';
            $query->where(fn ($where) => $where->where('notes', 'LIKE', $needle)->orWhere('expense_number', 'LIKE', $needle));
        }

        $page = $this->page($query, $request, fn (Expense $expense) => ExpensePresenter::summary($expense));

        return Response::structured(['expenses' => $page['rows'], 'page' => $page['page'], 'has_more' => $page['has_more']]);
    }
}
