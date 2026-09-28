<?php

namespace App\Domains\Purchases\Http\Controllers\Company;

use App\Domains\Metadata\Http\Resources\CustomFieldResource;
use App\Domains\Money\Models\Currency;
use App\Domains\Purchases\Application\PurchaseCustomFields;
use App\Domains\Purchases\Http\Requests\PurchaseOptionsRequest;
use App\Domains\Purchases\Models\Bill;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Purchases\Models\ExpenseCategory;
use App\Domains\Purchases\Models\RecurringCost;
use App\Domains\Purchases\Models\Supplier;
use App\Domains\Receivables\Models\PaymentMethod;
use App\Domains\Taxation\Models\TaxType;
use App\Platform\Http\Controller;
use Illuminate\Http\JsonResponse;
use Silber\Bouncer\BouncerFacade;

/**
 * The reference lists the purchasing forms need (categories, currencies,
 * purchase taxes, payment methods), and optionally the custom fields of one
 * form, without access to the settings screens those lists come from.
 */
class PurchaseOptionsController extends Controller
{
    public function __invoke(PurchaseOptionsRequest $request, PurchaseCustomFields $customFields): JsonResponse
    {
        $model = $request->validated('custom_field_model');

        abort_unless($this->allowed($model), 403);

        $company = (int) $request->header('company');
        $data = [];

        if ($model) {
            $data['custom_fields'] = CustomFieldResource::collection($customFields->definitions($company, $model));
        }

        $data['categories'] = ExpenseCategory::query()
            ->where('company_id', $company)
            ->orderBy('name')
            ->toBase()
            ->get(['id', 'name']);

        $data['currencies'] = Currency::query()->orderBy('code')->get();

        $data['taxes'] = TaxType::query()
            ->where('company_id', $company)
            ->where('type', TaxType::TYPE_GENERAL)
            ->where('transaction_type', TaxType::TRANSACTION_TYPE_PURCHASES)
            ->toBase()
            ->get(['id', 'name', 'percent', 'calculation_type', 'fixed_amount', 'compound_tax']);

        $data['payment_methods'] = PaymentMethod::query()
            ->where('company_id', $company)
            ->where('type', PaymentMethod::TYPE_GENERAL)
            ->toBase()
            ->get(['id', 'name']);

        return response()->json(['data' => $data]);
    }

    /**
     * A form's custom fields go to whoever can view, create or edit its
     * records; the lists alone to whoever can view suppliers or bills.
     */
    private function allowed(?string $model): bool
    {
        return match ($model) {
            'Supplier' => $this->canUse('supplier', Supplier::class),
            'Bill' => $this->canUse('bill', Bill::class) || $this->canUse('recurring-cost', RecurringCost::class),
            'Expense' => $this->canUse('expense', Expense::class) || $this->canUse('recurring-cost', RecurringCost::class),
            default => BouncerFacade::can('view-supplier', Supplier::class) || BouncerFacade::can('view-bill', Bill::class),
        };
    }

    private function canUse(string $entity, string $class): bool
    {
        foreach (['view', 'create', 'edit'] as $action) {
            if (BouncerFacade::can("{$action}-{$entity}", $class)) {
                return true;
            }
        }

        return false;
    }
}
