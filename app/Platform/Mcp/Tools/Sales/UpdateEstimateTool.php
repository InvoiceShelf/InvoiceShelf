<?php

namespace App\Platform\Mcp\Tools\Sales;

use App\Domains\Sales\Application\Composition\SalesDocumentComposer;
use App\Domains\Sales\Application\EstimateService;
use App\Domains\Sales\Http\Requests\EstimatesRequest;
use App\Domains\Sales\Jobs\GenerateEstimatePdfJob;
use App\Domains\Sales\Models\Estimate;
use App\Platform\Mcp\McpContext;
use App\Platform\Mcp\Presenters\DocumentPresenter;
use App\Platform\Mcp\Support\DomainRequestValidator;
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

#[Name('update_estimate')]
#[Title('Change an estimate')]
#[Description('Change an estimate. Only what is given changes; lines, when given, replace all the current ones. The totals are worked out again, and a tax already on the estimate keeps the rate it was applied at.')]
#[IsReadOnly(false)]
#[IsIdempotent(true)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class UpdateEstimateTool extends McpWriteTool
{
    use DescribesDocuments;

    public function __construct(
        private readonly SalesDocumentComposer $composer,
        private readonly DomainRequestValidator $validator,
        private readonly EstimateService $estimates,
    ) {}

    protected function ability(McpContext $context): ?array
    {
        return ['edit-estimate', Estimate::class];
    }

    public function schema(JsonSchema $schema): array
    {
        return [...$this->documentReference($schema, 'estimate'), ...$this->documentSchema($schema, 'estimate', creating: false)];
    }

    protected function write(Request $request, McpContext $context): array
    {
        $estimate = $this->findEstimate($request, $context);
        $this->authorizeRecord($context, 'update', $estimate);

        $payload = $this->composer->estimate($this->intent($request), $context->company->id, $context->user, $estimate);
        $validated = $this->validator->validate(EstimatesRequest::class, $payload, $context, 'PUT', ['estimate' => $estimate]);

        $estimate = $this->estimates->update(
            $estimate,
            attributes: $validated->getEstimatePayload(),
            items: $validated->input('items'),
            taxes: $validated->input('taxes') ?: null,
            customFields: $validated->input('customFields') ?: null,
        );

        GenerateEstimatePdfJob::dispatch($estimate, true);

        return DocumentPresenter::estimateDetail($estimate);
    }
}
