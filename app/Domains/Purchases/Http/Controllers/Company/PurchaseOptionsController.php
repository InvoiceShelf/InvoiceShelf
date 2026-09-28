<?php

namespace App\Domains\Purchases\Http\Controllers\Company;

use App\Domains\Metadata\Http\Resources\CustomFieldResource;
use App\Domains\Money\Models\Currency;
use App\Domains\Purchases\Application\PurchaseCustomFields;
use App\Domains\Purchases\Models\Bill;
use App\Domains\Purchases\Models\ExpenseCategory;
use App\Domains\Purchases\Models\Supplier;
use App\Domains\Receivables\Models\PaymentMethod;
use App\Domains\Taxation\Models\TaxType;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Silber\Bouncer\BouncerFacade;

class PurchaseOptionsController extends Controller
{
    public function __invoke(Request $request, PurchaseCustomFields $customFields)
    {
        $request->validate(['custom_field_model' => ['sometimes', Rule::in(['Supplier', 'Bill'])]]);
        $model = $request->input('custom_field_model');
        $canUse = fn (string $entity, string $class) => collect(['view', 'create', 'edit'])->contains(fn ($action) => BouncerFacade::can("{$action}-{$entity}", $class));
        $allowed = match ($model) {
            'Supplier' => $canUse('supplier', Supplier::class),
            'Bill' => $canUse('bill', Bill::class),
            default => BouncerFacade::can('view-supplier', Supplier::class) || BouncerFacade::can('view-bill', Bill::class),
        };
        abort_unless($allowed, 403);
        $company = (int) $request->header('company');

        return response()->json(['data' => [
            ...($model ? ['custom_fields' => CustomFieldResource::collection($customFields->definitions($company, $model))] : []),
            'categories' => ExpenseCategory::query()->where('company_id', $company)->orderBy('name')->toBase()->get(['id', 'name']),
            'currencies' => Currency::query()->orderBy('code')->get(),
            'taxes' => TaxType::query()->where('company_id', $company)->where('type', TaxType::TYPE_GENERAL)->where('transaction_type', TaxType::TRANSACTION_TYPE_PURCHASES)->toBase()->get(['id', 'name', 'percent', 'calculation_type', 'fixed_amount', 'compound_tax']),
            'payment_methods' => PaymentMethod::query()->where('company_id', $company)->where('type', PaymentMethod::TYPE_GENERAL)->toBase()->get(['id', 'name']),
        ]]);
    }
}
