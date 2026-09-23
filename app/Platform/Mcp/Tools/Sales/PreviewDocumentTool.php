<?php

namespace App\Platform\Mcp\Tools\Sales;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Money\Models\Currency;
use App\Domains\Sales\Application\Composition\SalesDocumentComposer;
use App\Domains\Sales\Http\Requests\EstimatesRequest;
use App\Domains\Sales\Http\Requests\InvoicesRequest;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Taxation\Models\TaxType;
use App\Platform\Mcp\McpContext;
use App\Platform\Mcp\Presenters\Money;
use App\Platform\Mcp\Support\DomainRequestValidator;
use App\Platform\Mcp\Tools\McpTool;
use App\Platform\Mcp\Tools\Sales\Concerns\DescribesDocuments;
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

#[Name('preview_document')]
#[Title('Preview an invoice or estimate')]
#[Description(<<<'TEXT'
    Work out an invoice or estimate without saving it: the number, dates and
    currency it would get, every line, discount and tax, and the totals exactly
    as they would be stored. Takes the same arguments as create_invoice or
    create_estimate, and reports the same problems. Show the result to the user
    before creating.
    TEXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class PreviewDocumentTool extends McpTool
{
    use DescribesDocuments;

    public function __construct(
        private readonly SalesDocumentComposer $composer,
        private readonly DomainRequestValidator $validator,
    ) {}

    protected function ability(McpContext $context): ?array
    {
        return ['viewAny', Invoice::class];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'kind' => $schema->string()->enum(['invoice', 'estimate'])->description('invoice unless given.'),
            ...$this->documentSchema($schema, 'invoice', creating: true),
            'expiry_date' => $schema->string()->format('date')->nullable()->description('For an estimate: its expiry date.'),
        ];
    }

    public function handle(Request $request, McpContext $context): Response|ResponseFactory
    {
        $request->validate(['kind' => ['nullable', 'in:invoice,estimate']]);

        $kind = $request->get('kind') ?? 'invoice';

        if ($kind === 'estimate' && ! $context->user->can('viewAny', Estimate::class)) {
            return Response::error('This connection may not see estimates.');
        }

        $intent = array_diff_key($this->intent($request), ['kind' => true]);
        $payload = $kind === 'invoice'
            ? $this->composer->invoice($intent, $context->company->id, $context->user)
            : $this->composer->estimate($intent, $context->company->id, $context->user);

        $validated = $this->validator->validate($kind === 'invoice' ? InvoicesRequest::class : EstimatesRequest::class, $payload, $context);
        $stored = $kind === 'invoice' ? $validated->getInvoicePayload() : $validated->getEstimatePayload();

        $currency = $stored['currency_id'];
        $names = TaxType::query()->whereIn('id', array_merge(
            array_column($payload['taxes'], 'tax_type_id'),
            ...array_map(fn (array $item) => array_column($item['taxes'], 'tax_type_id'), $payload['items']),
        ))->pluck('name', 'id');

        $taxes = fn (array $rows) => array_map(fn (array $row) => [
            'tax_type_id' => $row['tax_type_id'],
            'name' => $names[$row['tax_type_id']] ?? $row['name'],
            'percent' => $row['calculation_type'] === 'fixed' ? null : (float) $row['percent'],
            'compound' => (bool) $row['compound_tax'],
            'amount' => Money::of($row['amount'], $currency),
        ], $rows);

        return Response::structured([
            'kind' => $kind,
            'number' => $payload["{$kind}_number"],
            'customer' => ['id' => $payload['customer_id'], 'name' => Customer::query()->whereKey($payload['customer_id'])->value('name')],
            'date' => substr((string) $payload["{$kind}_date"], 0, 10),
            $kind === 'invoice' ? 'due_date' : 'expiry_date' => $payload[$kind === 'invoice' ? 'due_date' : 'expiry_date'],
            'template' => $payload['template_name'],
            'currency' => Currency::query()->whereKey($currency)->value('code'),
            'exchange_rate' => (float) $stored['exchange_rate'],
            'prices_include_tax' => (bool) $payload['tax_included'],
            'lines' => array_map(fn (array $item) => [
                'item_id' => $item['item_id'],
                'name' => $item['name'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit_name'],
                'unit_price' => Money::of($item['price'], $currency),
                'discount' => $item['discount_val'] ? Money::of($item['discount_val'], $currency) : null,
                'taxes' => $taxes($item['taxes']),
                'total' => Money::of($item['total'], $currency),
            ], $payload['items']),
            'sub_total' => Money::of($stored['sub_total'], $currency),
            'discount' => $payload['discount_val'] ? Money::of($payload['discount_val'], $currency) : null,
            'taxes' => $taxes($payload['taxes']),
            'tax' => Money::of($stored['tax'], $currency),
            'total' => Money::of($stored['total'], $currency),
            'total_in_company_currency' => Money::of($stored['base_total'], CompanySetting::getSetting('currency', $context->company->id)),
        ]);
    }
}
