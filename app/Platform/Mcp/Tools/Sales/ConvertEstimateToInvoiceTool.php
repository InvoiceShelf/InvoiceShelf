<?php

namespace App\Platform\Mcp\Tools\Sales;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Sales\Application\EstimateService;
use App\Domains\Sales\Models\Invoice;
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

#[Name('convert_estimate_to_invoice')]
#[Title('Turn an estimate into an invoice')]
#[Description(<<<'TEXT'
    Create a draft invoice from an estimate, with its lines, taxes and totals,
    today's date and the next invoice number. What happens to the estimate
    follows the company setting shown by get_company_context
    (converted_estimates_are): nothing, marked accepted, or deleted. When it
    would be deleted, confirm must be true; ask the user first.
    TEXT)]
#[IsReadOnly(false)]
#[IsIdempotent(false)]
#[IsDestructive(true)]
#[IsOpenWorld(false)]
class ConvertEstimateToInvoiceTool extends McpWriteTool
{
    use DescribesDocuments;

    public function __construct(
        private readonly EstimateService $estimates,
    ) {}

    protected function ability(McpContext $context): ?array
    {
        return ['create', Invoice::class];
    }

    protected function creates(): bool
    {
        return true;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->documentReference($schema, 'estimate'),
            'confirm' => $schema->boolean()->description('Must be true when the company deletes estimates once converted.'),
            ...$this->idempotencySchema($schema),
        ];
    }

    protected function write(Request $request, McpContext $context): array
    {
        $estimate = $this->findEstimate($request, $context);
        $this->authorizeRecord($context, 'view', $estimate);

        $deletes = CompanySetting::getSetting('estimate_convert_action', $context->company->id) === 'delete_estimate';

        if ($deletes && $request->get('confirm') !== true) {
            $this->refuse("Converting deletes estimate {$estimate->estimate_number} in this company. Nothing was changed; ask the user, then call again with confirm set to true.", 'confirm');
        }

        return DocumentPresenter::invoiceDetail($this->estimates->convertToInvoice($estimate));
    }
}
