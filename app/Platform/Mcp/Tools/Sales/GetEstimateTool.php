<?php

namespace App\Platform\Mcp\Tools\Sales;

use App\Domains\Sales\Models\Estimate;
use App\Platform\Mcp\McpContext;
use App\Platform\Mcp\Presenters\DocumentPresenter;
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

#[Name('get_estimate')]
#[Title('Get an estimate')]
#[Description('One estimate (quote) in full, by its number or id: lines, discounts, taxes and totals, status, expiry date, notes and custom fields.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class GetEstimateTool extends McpTool
{
    protected function ability(McpContext $context): ?array
    {
        return ['viewAny', Estimate::class];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'estimate_number' => $schema->string()->description('The estimate number, for example "EST-000004".'),
            'estimate_id' => $schema->integer()->description('The estimate id, when the number is not known.'),
        ];
    }

    public function handle(Request $request, McpContext $context): Response|ResponseFactory
    {
        $request->validate([
            'estimate_number' => ['required_without:estimate_id', 'nullable', 'string'],
            'estimate_id' => ['required_without:estimate_number', 'nullable', 'integer'],
        ]);

        $estimate = Estimate::query()
            ->with(['customer', 'currency'])
            ->where('company_id', $context->company->id)
            ->when($request->get('estimate_id'), fn ($query, $id) => $query->whereKey($id))
            ->when(! $request->get('estimate_id'), fn ($query) => $query->where('estimate_number', $request->get('estimate_number')))
            ->first();

        if (! $estimate) {
            return Response::error('There is no such estimate in the company.');
        }

        $this->authorizeRecord($context, 'view', $estimate);

        return Response::structured(DocumentPresenter::estimateDetail($estimate));
    }
}
