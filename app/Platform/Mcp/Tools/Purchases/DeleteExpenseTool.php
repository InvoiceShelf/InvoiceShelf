<?php

namespace App\Platform\Mcp\Tools\Purchases;

use App\Domains\Purchases\Models\Expense;
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

#[Name('delete_expense')]
#[Title('Delete an expense')]
#[Description('Delete an expense for good, with its receipt. Ask the user first and set confirm to true.')]
#[IsReadOnly(false)]
#[IsIdempotent(true)]
#[IsDestructive(true)]
#[IsOpenWorld(false)]
class DeleteExpenseTool extends McpWriteTool
{
    use RequiresConfirmation;

    protected function ability(McpContext $context): ?array
    {
        return ['delete-expense', Expense::class];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'expense_id' => $schema->integer()->description('The expense, from search_expenses.')->required(),
            ...$this->confirmSchema($schema, 'the expense is deleted for good'),
        ];
    }

    protected function write(Request $request, McpContext $context): array
    {
        $expense = Expense::query()->where('company_id', $context->company->id)->find($request->get('expense_id'))
            ?? $this->refuse('There is no expense with this id in the company.', 'expense_id');

        $this->authorizeRecord($context, 'delete', $expense);

        if ($aborted = $this->unconfirmed($request, 'Deleting removes the expense for good')) {
            return $aborted;
        }

        Expense::destroy($expense->id);

        return ['id' => $expense->id, 'deleted' => true];
    }
}
