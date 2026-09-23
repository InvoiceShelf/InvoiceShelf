<?php

namespace App\Platform\Mcp\Tools\Sales\Concerns;

use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Mcp\McpContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;

/**
 * The arguments that describe an invoice or an estimate, the way
 * SalesDocumentComposer reads them, and finding one by number or id.
 */
trait DescribesDocuments
{
    /**
     * @param  'invoice'|'estimate'  $kind
     * @return array<string, mixed>
     */
    protected function documentSchema(JsonSchema $schema, string $kind, bool $creating): array
    {
        $taxIds = fn (string $where) => $schema->array()->items($schema->integer())
            ->description("Sales tax type ids from get_company_context, when the company taxes {$where}.");
        $answers = fn (string $what) => $schema->array()->items($schema->object([
            'slug' => $schema->string()->description('The custom field slug, from get_company_context.'),
            'value' => $schema->string(),
        ]))->description("Answers to the custom fields that apply to {$what}; required ones must be answered.");

        $line = $schema->object([
            'item_id' => $schema->integer()->description('A catalogue item (search_items); it fills in the name, description, unit, price and, per line, taxes the line leaves out.'),
            'name' => $schema->string()->description('What is sold. Required unless item_id is given.'),
            'description' => $schema->string(),
            'quantity' => $schema->number()->description('1 unless given. Fractions and negative quantities are allowed.'),
            'unit_price' => $schema->string()->description('Price of one unit in the customer\'s currency, a decimal in major units like "120.00". Taken from the item when left out.'),
            'unit' => $schema->string()->description('Unit name, like "hours".'),
            'discount' => $schema->string()->description('Line discount, when the company discounts each line: an amount like "5.00" or a percent like "10".'),
            'discount_type' => $schema->string()->enum(['fixed', 'percentage']),
            'tax_type_ids' => $taxIds('each line'),
            'custom_fields' => $answers('lines'),
        ]);

        [$dateLabel, $closing, $closingLabel] = $kind === 'invoice'
            ? ['Invoice date', 'due_date', 'Due date; the company\'s automatic one when left out, none when null.']
            : ['Estimate date', 'expiry_date', 'Expiry date; the company\'s automatic one when left out, none when null.'];

        $customer = $schema->integer()->description('The customer, from search_customers. The document is in their currency.');
        $lines = $schema->array()->items($line)->min(1)->description($creating
            ? 'The lines, at least one.'
            : 'The lines, replacing all of the current ones. Leave out to keep them.');

        return [
            'customer_id' => $creating ? $customer->required() : $customer,
            'date' => $schema->string()->format('date')->description("{$dateLabel}, YYYY-MM-DD; today when left out."),
            $closing => $schema->string()->format('date')->nullable()->description($closingLabel),
            'number' => $schema->string()->description('Leave out to take the next number in the company\'s series.'),
            'reference_number' => $schema->string(),
            'notes' => $schema->string(),
            'template' => $schema->string()->description('A template name from get_company_context.'),
            'prices_include_tax' => $schema->boolean()->description('Whether unit prices already include tax; only where the company allows it.'),
            'discount' => $schema->string()->description('Discount on the whole document, when the company discounts documents: an amount like "25.00" or a percent like "10".'),
            'discount_type' => $schema->string()->enum(['fixed', 'percentage']),
            'tax_type_ids' => $taxIds('whole documents'),
            'exchange_rate' => $schema->number()->description('For a customer in another currency: the value of one unit of their currency in the company\'s. Looked up when left out.'),
            'custom_fields' => $answers($kind === 'invoice' ? 'invoices' : 'estimates'),
            'lines' => $creating ? $lines->required() : $lines,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function intent(Request $request): array
    {
        return array_diff_key($request->all(), array_flip(['idempotency_key', 'invoice_id', 'invoice_number', 'estimate_id', 'estimate_number']));
    }

    /**
     * @throws ValidationException
     */
    protected function findInvoice(Request $request, McpContext $context): Invoice
    {
        return $this->findDocument(Invoice::query(), 'invoice', $request, $context);
    }

    /**
     * @throws ValidationException
     */
    protected function findEstimate(Request $request, McpContext $context): Estimate
    {
        return $this->findDocument(Estimate::query(), 'estimate', $request, $context);
    }

    /**
     * @return array<string, mixed>
     */
    protected function documentReference(JsonSchema $schema, string $kind): array
    {
        return [
            "{$kind}_number" => $schema->string()->description("The {$kind} number."),
            "{$kind}_id" => $schema->integer()->description("The {$kind} id, when the number is not known."),
        ];
    }

    private function findDocument($query, string $kind, Request $request, McpContext $context)
    {
        $id = $request->get("{$kind}_id");
        $number = $request->get("{$kind}_number");

        if (! $id && ! $number) {
            throw ValidationException::withMessages(["{$kind}_number" => "Name the {$kind} by {$kind}_number or {$kind}_id."]);
        }

        $document = $query->where('company_id', $context->company->id)
            ->when($id, fn ($query) => $query->whereKey($id), fn ($query) => $query->where("{$kind}_number", $number))
            ->first();

        if (! $document) {
            throw ValidationException::withMessages(["{$kind}_number" => "There is no such {$kind} in the company."]);
        }

        return $document;
    }
}
