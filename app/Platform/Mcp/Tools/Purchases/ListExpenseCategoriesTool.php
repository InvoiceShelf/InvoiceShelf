<?php

namespace App\Platform\Mcp\Tools\Purchases;

use App\Domains\Purchases\Models\ExpenseCategory;
use App\Platform\Mcp\McpContext;
use App\Platform\Mcp\Tools\McpTool;
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

#[Name('list_expense_categories')]
#[Title('List expense categories')]
#[Description('Every expense category of the company, with its id, name and description.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class ListExpenseCategoriesTool extends McpTool
{
    protected function ability(McpContext $context): ?array
    {
        return ['viewAny', ExpenseCategory::class];
    }

    public function handle(Request $request, McpContext $context): ResponseFactory
    {
        $categories = ExpenseCategory::query()
            ->where('company_id', $context->company->id)
            ->orderBy('name')
            ->toBase()
            ->get(['id', 'name', 'description'])
            ->map(fn (object $row) => ['id' => (int) $row->id, 'name' => $row->name, 'description' => $row->description])
            ->all();

        return Response::structured(['categories' => $categories]);
    }
}
