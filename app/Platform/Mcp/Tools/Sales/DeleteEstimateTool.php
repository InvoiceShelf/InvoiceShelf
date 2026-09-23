<?php

namespace App\Platform\Mcp\Tools\Sales;

use App\Domains\Sales\Models\Estimate;
use App\Platform\Mcp\McpContext;
use App\Platform\Mcp\Tools\Concerns\RequiresConfirmation;
use App\Platform\Mcp\Tools\McpWriteTool;
use App\Platform\Mcp\Tools\Sales\Concerns\DescribesDocuments;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('delete_estimate')]
#[Title('Delete an estimate')]
#[Description('Delete an estimate for good. Invoices already made from it stay. Ask the user first and set confirm to true.')]
#[IsReadOnly(false)]
#[IsIdempotent(true)]
#[IsDestructive(true)]
#[IsOpenWorld(false)]
class DeleteEstimateTool extends McpWriteTool
{
    use DescribesDocuments;
    use RequiresConfirmation;

    protected function ability(McpContext $context): ?array
    {
        return ['delete-estimate', Estimate::class];
    }

    public function schema(JsonSchema $schema): array
    {
        return [...$this->documentReference($schema, 'estimate'), ...$this->confirmSchema($schema, 'the estimate is deleted for good')];
    }

    protected function write(Request $request, McpContext $context): array
    {
        $estimate = $this->findEstimate($request, $context);
        $this->authorizeRecord($context, 'delete', $estimate);

        if ($aborted = $this->unconfirmed($request, "Deleting removes estimate {$estimate->estimate_number} for good")) {
            return $aborted;
        }

        Estimate::destroy($estimate->id);

        return ['id' => $estimate->id, 'number' => $estimate->estimate_number, 'deleted' => true];
    }
}
