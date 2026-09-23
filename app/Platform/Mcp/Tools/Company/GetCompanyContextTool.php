<?php

namespace App\Platform\Mcp\Tools\Company;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Catalog\Models\Unit;
use App\Domains\Metadata\Models\CustomField;
use App\Domains\Money\Models\Currency;
use App\Domains\Purchases\Models\ExpenseCategory;
use App\Domains\Receivables\Models\PaymentMethod;
use App\Domains\Taxation\Models\TaxType;
use App\Platform\Mcp\McpContext;
use App\Platform\Mcp\Tools\McpTool;
use App\Platform\Pdf\Rendering\PdfTemplateUtils;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_company_context')]
#[Title('Company context')]
#[Description(<<<'TEXT'
    Describe the company this connection works in: its base currency, how taxes
    and discounts are applied (per line or per document, prices with or without
    tax), the sales tax types, payment methods, units, document templates,
    custom fields (and which are required), expense categories, and what this
    connection may do. Call this first, before reading or writing anything.
    TEXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class GetCompanyContextTool extends McpTool
{
    private const SETTINGS = [
        'currency', 'language', 'time_zone', 'carbon_date_format', 'fiscal_year',
        'tax_per_item', 'discount_per_item', 'tax_included', 'tax_included_by_default',
        'invoice_set_due_date_automatically', 'invoice_due_date_days',
        'estimate_convert_action',
    ];

    public function handle(McpContext $context): ResponseFactory
    {
        $company = $context->company;
        $settings = CompanySetting::getSettings(self::SETTINGS, $company->id);
        $currency = Currency::find($settings->get('currency'));

        return Response::structured([
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
            ],
            'connection' => [
                'access' => $context->connection->access,
                'user' => $context->user->name,
            ],
            'currency' => $currency ? [
                'code' => $currency->code,
                'name' => $currency->name,
                'symbol' => $currency->symbol,
                'precision' => (int) $currency->precision,
            ] : null,
            'preferences' => [
                'language' => $settings->get('language'),
                'time_zone' => $settings->get('time_zone'),
                'date_format' => $settings->get('carbon_date_format'),
                'fiscal_year' => $settings->get('fiscal_year'),
                'invoice_due_after_days' => $settings->get('invoice_set_due_date_automatically') === 'YES'
                    ? (int) $settings->get('invoice_due_date_days')
                    : null,
                'converted_estimates_are' => $settings->get('estimate_convert_action'),
            ],
            'tax_setup' => [
                'taxes_per_line' => $settings->get('tax_per_item') === 'YES',
                'discounts_per_line' => $settings->get('discount_per_item') === 'YES',
                'prices_may_include_tax' => $settings->get('tax_included') === 'YES',
                'prices_include_tax_by_default' => $settings->get('tax_included_by_default') === 'YES',
            ],
            'sales_tax_types' => TaxType::query()
                ->where('company_id', $company->id)
                ->where('type', TaxType::TYPE_GENERAL)
                ->where(fn ($query) => $query
                    ->whereNull('transaction_type')
                    ->orWhere('transaction_type', TaxType::TRANSACTION_TYPE_SALES))
                ->orderBy('name')
                ->get()
                ->map(fn (TaxType $tax) => [
                    'id' => $tax->id,
                    'name' => $tax->name,
                    'percent' => $tax->percent,
                    'fixed_amount' => $tax->fixed_amount !== null ? number_format($tax->fixed_amount / 100, 2, '.', '') : null,
                    'calculation' => $tax->calculation_type ?? 'percentage',
                    'compound' => (bool) $tax->compound_tax,
                ])->all(),
            'payment_methods' => $this->idsAndNames(PaymentMethod::query()
                ->where('company_id', $company->id)
                ->where('type', PaymentMethod::TYPE_GENERAL)),
            'units' => $this->idsAndNames(Unit::query()->where('company_id', $company->id)),
            'expense_categories' => $this->idsAndNames(ExpenseCategory::query()->where('company_id', $company->id)),
            'templates' => [
                'invoice' => array_column(PdfTemplateUtils::getFormattedTemplates('invoice', ''), 'name'),
                'estimate' => array_column(PdfTemplateUtils::getFormattedTemplates('estimate', ''), 'name'),
            ],
            'custom_fields' => CustomField::query()
                ->where('company_id', $company->id)
                ->orderBy('model_type')
                ->orderBy('order')
                ->get()
                ->map(fn (CustomField $field) => [
                    'slug' => $field->slug,
                    'label' => $field->label,
                    'applies_to' => $field->model_type,
                    'type' => $field->type,
                    'required' => (bool) $field->is_required,
                    'options' => $field->options ?: null,
                ])->all(),
        ]);
    }

    /**
     * Plain id and name pairs, read without hydrating the models (their
     * appended attributes format dates for the SPA).
     *
     * @param  Builder<Model>  $query
     * @return list<array{id: int, name: string}>
     */
    private function idsAndNames(Builder $query): array
    {
        return $query->toBase()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (object $row): array => ['id' => (int) $row->id, 'name' => (string) $row->name])
            ->all();
    }
}
