<?php

namespace App\Platform\Mcp\Tools\Sales;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Mcp\McpContext;
use App\Platform\Mcp\Presenters\DocumentPresenter;
use App\Platform\Mcp\Presenters\Money;
use App\Platform\Mcp\Tools\Concerns\Paginates;
use App\Platform\Mcp\Tools\McpTool;
use Carbon\CarbonImmutable;
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

#[Name('list_overdue_invoices')]
#[Title('List overdue invoices')]
#[Description('Issued invoices with money still owing and a due date in the past, the longest overdue first, with how many days late each is and the total overdue in the company currency.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class ListOverdueInvoicesTool extends McpTool
{
    use Paginates;

    protected function ability(McpContext $context): ?array
    {
        return ['viewAny', Invoice::class];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'customer_id' => $schema->integer()->description('Only this customer\'s invoices.'),
            ...$this->pageSchema($schema),
        ];
    }

    public function handle(Request $request, McpContext $context): ResponseFactory
    {
        $request->validate(['customer_id' => ['nullable', 'integer']]);

        $query = OverdueInvoices::constrain(Invoice::query()->where('company_id', $context->company->id), $context->company->id)
            ->when($request->get('customer_id'), fn ($query, $id) => $query->where('customer_id', $id));

        $total = (int) $query->clone()->sum('base_due_amount');
        $settings = CompanySetting::getSettings(['currency', 'time_zone'], $context->company->id);
        $today = CarbonImmutable::now($settings->get('time_zone') ?: config('app.timezone'))->startOfDay();

        $page = $this->page(
            $query->with('customer')->orderBy('due_date')->orderBy('id'),
            $request,
            fn (Invoice $invoice) => DocumentPresenter::invoiceSummary($invoice) + [
                'days_overdue' => (int) CarbonImmutable::parse($invoice->getRawOriginal('due_date'), $today->getTimezone())->startOfDay()->diffInDays($today),
            ],
        );

        return Response::structured([
            'total_overdue' => Money::of($total, $settings->get('currency')),
            'invoices' => $page['rows'],
            'page' => $page['page'],
            'has_more' => $page['has_more'],
        ]);
    }
}
