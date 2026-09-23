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

#[Name('create_estimate')]
#[Title('Create an estimate')]
#[Description(<<<'TEXT'
    Create a draft estimate (a quote). Give the customer and the lines; the
    server works out the number, dates, template, currency and every discount,
    tax and total the way the estimate form does. Call preview_document first
    to check the totals with the user.
    TEXT)]
#[IsReadOnly(false)]
#[IsIdempotent(false)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class CreateEstimateTool extends McpWriteTool
{
    use DescribesDocuments;

    public function __construct(
        private readonly SalesDocumentComposer $composer,
        private readonly DomainRequestValidator $validator,
        private readonly EstimateService $estimates,
    ) {}

    protected function ability(McpContext $context): ?array
    {
        return ['create', Estimate::class];
    }

    protected function creates(): bool
    {
        return true;
    }

    public function schema(JsonSchema $schema): array
    {
        return [...$this->documentSchema($schema, 'estimate', creating: true), ...$this->idempotencySchema($schema)];
    }

    protected function write(Request $request, McpContext $context): array
    {
        $payload = $this->composer->estimate($this->intent($request), $context->company->id, $context->user);
        $validated = $this->validator->validate(EstimatesRequest::class, $payload, $context);

        $estimate = $this->estimates->create(
            attributes: $validated->getEstimatePayload(),
            items: $validated->input('items'),
            taxes: $validated->input('taxes') ?: null,
            customFields: $validated->input('customFields') ?: null,
        );

        GenerateEstimatePdfJob::dispatch($estimate);

        return DocumentPresenter::estimateDetail($estimate);
    }
}
