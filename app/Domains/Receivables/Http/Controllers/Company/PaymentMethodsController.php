<?php

namespace App\Domains\Receivables\Http\Controllers\Company;

use App\Domains\Purchases\Application\RecurringTemplates;
use App\Domains\Purchases\Models\SupplierPayment;
use App\Domains\Purchases\Models\SupplierRefund;
use App\Domains\Receivables\Http\Requests\PaymentMethodRequest;
use App\Domains\Receivables\Http\Resources\PaymentMethodResource;
use App\Domains\Receivables\Models\PaymentMethod;
use App\Platform\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The manual payment methods of a company: the labels an admin maintains by
 * hand. Methods registered by gateway modules live in the same table but are
 * owned by the module platform and never surface here.
 */
class PaymentMethodsController extends Controller
{
    /**
     * Paginated methods of the active company, newest first.
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', PaymentMethod::class);

        // Filters come first: the method_id filter widens the query with an OR,
        // so the type and company conditions have to close over it.
        $paymentMethods = PaymentMethod::applyFilters($request->all())
            ->where('type', PaymentMethod::TYPE_GENERAL)
            ->whereCompany()
            ->latest()
            ->paginateData($request->input('limit', 5));

        return PaymentMethodResource::collection($paymentMethods);
    }

    /**
     * Add a manual method.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function store(PaymentMethodRequest $request)
    {
        $this->authorize('create', PaymentMethod::class);

        $paymentMethod = PaymentMethod::create($request->getPaymentMethodPayload());

        return new PaymentMethodResource($paymentMethod);
    }

    /**
     * One method.
     *
     * @return JsonResponse
     */
    public function show(PaymentMethod $paymentMethod)
    {
        $this->authorize('view', $paymentMethod);

        return new PaymentMethodResource($paymentMethod);
    }

    /**
     * Rename a method.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function update(PaymentMethodRequest $request, PaymentMethod $paymentMethod)
    {
        $this->authorize('update', $paymentMethod);

        $paymentMethod->update($request->getPaymentMethodPayload());

        return new PaymentMethodResource($paymentMethod);
    }

    /**
     * Drop a method, unless money already points at it. Both refusals are
     * domain conflicts (422), each carrying the key the SPA switches on.
     *
     * @return JsonResponse
     */
    public function destroy(PaymentMethod $paymentMethod)
    {
        $this->authorize('delete', $paymentMethod);

        if ($paymentMethod->payments()->exists()) {
            return respondJson('payments_attached', 'Payments Attached.');
        }

        if ($paymentMethod->expenses()->exists()) {
            return respondJson('expenses_attached', 'Expenses Attached.');
        }

        if (SupplierPayment::query()->where('payment_method_id', $paymentMethod->id)->exists()
            || SupplierRefund::query()->where('payment_method_id', $paymentMethod->id)->exists()
            || RecurringTemplates::usePaymentMethod($paymentMethod->company_id, $paymentMethod->id)) {
            return respondJson('payments_attached', 'Payments Attached.');
        }
        $paymentMethod->delete();

        return response()->json([
            'success' => 'Payment method deleted successfully',
        ]);
    }
}
