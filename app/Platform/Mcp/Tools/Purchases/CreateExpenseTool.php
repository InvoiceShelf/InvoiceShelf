<?php

namespace App\Platform\Mcp\Tools\Purchases;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Money\Models\Currency;
use App\Domains\Purchases\Application\ExpenseService;
use App\Domains\Purchases\Http\Requests\ExpenseRequest;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Purchases\Models\ExpenseCategory;
use App\Domains\Receivables\Models\PaymentMethod;
use App\Platform\Mcp\McpContext;
use App\Platform\Mcp\Presenters\ExpensePresenter;
use App\Platform\Mcp\Support\DomainRequestValidator;
use App\Platform\Mcp\Tools\McpWriteTool;
use App\Support\MinorUnits;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('create_expense')]
#[Title('Record an expense')]
#[Description('Record money spent: its category, amount and date, optionally the customer it is billed to and how it was paid. Receipts are attached in the app.')]
#[IsReadOnly(false)]
#[IsIdempotent(false)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class CreateExpenseTool extends McpWriteTool
{
    public function __construct(
        private readonly DomainRequestValidator $validator,
        private readonly ExpenseService $expenses,
    ) {}

    protected function ability(McpContext $context): ?array
    {
        return ['create', Expense::class];
    }

    protected function creates(): bool
    {
        return true;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'category' => $schema->string()->description('An expense category name or id (list_expense_categories).')->required(),
            'amount' => $schema->string()->description('What was spent, a decimal in major units like "42.50".')->required(),
            'date' => $schema->string()->format('date')->description('YYYY-MM-DD; today when left out.'),
            'currency' => $schema->string()->description('ISO code of the currency spent; the company currency when left out.'),
            'exchange_rate' => $schema->number()->description('For another currency: the value of one unit of it in the company\'s. Required then.'),
            'customer_id' => $schema->integer()->description('The customer the expense is billed to, if any.'),
            'payment_method' => $schema->string()->description('A payment method name or id.'),
            'notes' => $schema->string(),
            ...$this->idempotencySchema($schema),
        ];
    }

    protected function write(Request $request, McpContext $context): array
    {
        $companyId = $context->company->id;
        $settings = CompanySetting::getSettings(['currency', 'time_zone'], $companyId);

        $category = $this->byNameOrId(ExpenseCategory::query()->where('company_id', $companyId), $request->get('category'), 'category', 'expense category');
        $method = $request->get('payment_method')
            ? $this->byNameOrId(PaymentMethod::query()->where('company_id', $companyId)->where('type', PaymentMethod::TYPE_GENERAL), $request->get('payment_method'), 'payment_method', 'payment method')
            : null;

        $amount = MinorUnits::fromMajor($request->get('amount'));

        if ($amount === null || $amount < 0) {
            $this->refuse('The amount is a decimal in major units with at most two decimals, like "42.50".', 'amount');
        }

        $currencyId = $settings->get('currency');

        if ($request->get('currency')) {
            $currencyId = Currency::query()->where('code', strtoupper((string) $request->get('currency')))->value('id')
                ?? $this->refuse('There is no currency with the code '.$request->get('currency').'.', 'currency');
        }

        if ((string) $currencyId !== (string) $settings->get('currency') && ! is_numeric($request->get('exchange_rate'))) {
            $this->refuse('An expense in another currency needs exchange_rate, the value of one unit of it in the company currency.', 'exchange_rate');
        }

        if ($request->get('customer_id') && ! Customer::query()->where('company_id', $companyId)->whereKey($request->get('customer_id'))->exists()) {
            $this->refuse('There is no customer with this id in the company.', 'customer_id');
        }

        $validated = $this->validator->validate(ExpenseRequest::class, [
            'expense_date' => $request->get('date') ?? CarbonImmutable::now($settings->get('time_zone') ?: config('app.timezone'))->toDateString(),
            'expense_category_id' => $category->id,
            'amount' => $amount,
            'currency_id' => $currencyId,
            'exchange_rate' => $request->get('exchange_rate'),
            'customer_id' => $request->get('customer_id'),
            'payment_method_id' => $method?->id,
            'notes' => $request->get('notes'),
        ], $context);

        $expense = $this->expenses->create(attributes: $validated->getExpensePayload(), taxes: null, receipt: null, customFields: null);

        return ExpensePresenter::summary($expense->fresh(['category', 'customer', 'paymentMethod']));
    }

    private function byNameOrId($query, mixed $given, string $field, string $what)
    {
        $rows = $query->get(['id', 'name']);

        $found = is_numeric($given)
            ? $rows->firstWhere('id', (int) $given)
            : $rows->first(fn ($row) => mb_strtolower($row->name) === mb_strtolower(trim((string) $given)));

        return $found ?? $this->refuse("The {$what} is one of: ".$rows->pluck('name')->implode(', ').'.', $field);
    }
}
