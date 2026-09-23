<?php

namespace App\Platform\Mcp\Tools\Sales;

use App\Domains\Sales\Application\EstimateService;
use App\Domains\Sales\Models\Estimate;
use App\Platform\Mcp\McpContext;
use App\Platform\Mcp\Presenters\DocumentPresenter;
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

#[Name('change_estimate_status')]
#[Title('Mark an estimate sent, accepted or rejected')]
#[Description('Record what became of an estimate: SENT (delivered outside the app; nothing is emailed), ACCEPTED or REJECTED by the customer.')]
#[IsReadOnly(false)]
#[IsIdempotent(true)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class ChangeEstimateStatusTool extends McpWriteTool
{
    use DescribesDocuments;

    public const STATUSES = [Estimate::STATUS_SENT, Estimate::STATUS_ACCEPTED, Estimate::STATUS_REJECTED];

    public function __construct(
        private readonly EstimateService $estimates,
    ) {}

    protected function ability(McpContext $context): ?array
    {
        return ['send-estimate', Estimate::class];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->documentReference($schema, 'estimate'),
            'status' => $schema->string()->enum(self::STATUSES)->required(),
        ];
    }

    protected function write(Request $request, McpContext $context): array
    {
        $request->validate(['status' => ['required', 'in:'.implode(',', self::STATUSES)]]);

        $estimate = $this->findEstimate($request, $context);
        $this->authorizeRecord($context, 'send estimate', $estimate);

        $this->estimates->changeStatus($estimate, $request->get('status'));

        return DocumentPresenter::estimateSummary($estimate->fresh());
    }
}
