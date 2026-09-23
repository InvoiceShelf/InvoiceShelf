<?php

namespace App\Platform\Mcp\Tools\Sales;

use App\Domains\Sales\Models\Invoice;
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

#[Name('get_invoice')]
#[Title('Get an invoice')]
#[Description('One invoice or credit note in full, by its number or id: lines, discounts, taxes and totals, what is paid and still due, the payments and credit notes against it, notes and custom fields.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class GetInvoiceTool extends McpTool
{
    protected function ability(McpContext $context): ?array
    {
        return ['viewAny', Invoice::class];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'invoice_number' => $schema->string()->description('The invoice number, for example "INV-000012".'),
            'invoice_id' => $schema->integer()->description('The invoice id, when the number is not known.'),
        ];
    }

    public function handle(Request $request, McpContext $context): Response|ResponseFactory
    {
        $request->validate([
            'invoice_number' => ['required_without:invoice_id', 'nullable', 'string'],
            'invoice_id' => ['required_without:invoice_number', 'nullable', 'integer'],
        ]);

        $invoice = Invoice::query()
            ->with(['customer', 'currency'])
            ->where('company_id', $context->company->id)
            ->when($request->get('invoice_id'), fn ($query, $id) => $query->whereKey($id))
            ->when(! $request->get('invoice_id'), fn ($query) => $query->where('invoice_number', $request->get('invoice_number')))
            ->first();

        if (! $invoice) {
            return Response::error('There is no such invoice in the company.');
        }

        $this->authorizeRecord($context, 'view', $invoice);

        return Response::structured(DocumentPresenter::invoiceDetail($invoice));
    }
}
