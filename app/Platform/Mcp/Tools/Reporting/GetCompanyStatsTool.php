<?php

namespace App\Platform\Mcp\Tools\Reporting;

use App\Domains\Accounts\Models\CompanySetting;
use App\Platform\Mcp\McpContext;
use App\Platform\Mcp\Presenters\Money;
use App\Platform\Mcp\Queries\CompanyStatsQuery;
use App\Platform\Mcp\Tools\Concerns\ResolvesPeriod;
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

#[Name('get_company_stats')]
#[Title('Company figures')]
#[Description(<<<'TEXT'
    The company's money over a period, in its own currency: invoiced (issued
    invoices less credit notes; drafts are not counted), received, spent on
    expenses and the net of the two, with counts. Also what customers owe right
    now, split into overdue, due within 30 days and due later, whatever the
    period. Use this for questions like "how much did we invoice this quarter".
    TEXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class GetCompanyStatsTool extends McpTool
{
    use ResolvesPeriod;

    public function __construct(
        private readonly CompanyStatsQuery $stats,
    ) {}

    protected function ability(McpContext $context): ?array
    {
        return ['view dashboard', $context->company];
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->periodSchema($schema, 'this_fiscal_year');
    }

    public function handle(Request $request, McpContext $context): ResponseFactory
    {
        $companyId = $context->company->id;
        $period = $this->period($request, $companyId, 'this_fiscal_year');
        $settings = CompanySetting::getSettings(['currency', 'time_zone'], $companyId);
        $currency = $settings->get('currency');
        $today = CarbonImmutable::now($settings->get('time_zone') ?: config('app.timezone'));

        $totals = $this->stats->totals($companyId, $period['from'], $period['to'], $today);
        $owed = $totals['receivables'];

        return Response::structured([
            'period' => $period,
            'invoiced' => Money::of($totals['invoiced'], $currency),
            'invoice_count' => $totals['invoice_count'],
            'credited' => Money::of($totals['credited'], $currency),
            'received' => Money::of($totals['received'], $currency),
            'payment_count' => $totals['payment_count'],
            'expenses' => Money::of($totals['expenses'], $currency),
            'expense_count' => $totals['expense_count'],
            'net_income' => Money::of($totals['net_income'], $currency),
            'receivables_now' => [
                'outstanding' => Money::of($owed['outstanding'], $currency),
                'outstanding_invoices' => $owed['outstanding_count'],
                'overdue' => Money::of($owed['overdue'], $currency),
                'overdue_invoices' => $owed['overdue_count'],
                'due_within_30_days' => Money::of($owed['due_soon'], $currency),
                'due_later' => Money::of($owed['due_later'], $currency),
            ],
        ]);
    }
}
