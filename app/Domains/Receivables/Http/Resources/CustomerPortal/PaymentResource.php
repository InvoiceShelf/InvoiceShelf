<?php

namespace App\Domains\Receivables\Http\Resources\CustomerPortal;

use App\Domains\Accounts\Http\Resources\CustomerPortal\CompanyResource;
use App\Domains\Contacts\Http\Resources\CustomerPortal\CustomerResource;
use App\Domains\Metadata\Http\Resources\CustomerPortal\CustomFieldValueResource;
use App\Domains\Money\Http\Resources\CustomerPortal\CurrencyResource;
use App\Domains\Sales\Http\Resources\CustomerPortal\InvoiceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * A received payment as the customer portal publishes it.
 *
 * Near enough the admin payload, minus the two fields that describe internal
 * bookkeeping rather than the receipt: who recorded the payment, and its
 * position in the company's numbering sequence.
 *
 * One further difference is worth naming because it runs the opposite way to
 * the invoice payloads: the notes here are the stored text as written, not the
 * placeholder-interpolated rendering the admin payload publishes. Portal
 * readers therefore see any unresolved placeholders verbatim. That is the
 * established behaviour and is kept as-is.
 *
 * Everything else follows the admin resource: allocation rows are always
 * published and are fetched with their invoices when the caller has not loaded
 * them, the four settlement figures are derived from those rows, a missing
 * `base_amount` is recomputed from the amount and the rate (a falsy rate
 * standing in as one), and each trailing association is gated on its own
 * existence probe -- a query apiece per serialised row.
 */
class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        $payment = $this->resource;

        $allocations = $payment->relationLoaded('allocations')
            ? $payment->allocations
            : $payment->allocations()->with('invoice')->get();

        $allocated = (int) $allocations->sum('amount');
        $baseAllocated = (int) $allocations->sum('base_amount');
        $baseAmount = $this->baseAmount();

        return [
            'id' => $payment->id,
            'payment_number' => $payment->payment_number,
            'payment_date' => $payment->payment_date,
            'notes' => $payment->notes,
            'amount' => $payment->amount,
            'unique_hash' => $payment->unique_hash,
            'company_id' => $payment->company_id,
            'payment_method_id' => $payment->payment_method_id,
            'customer_id' => $payment->customer_id,
            'exchange_rate' => $payment->exchange_rate,
            'base_amount' => $baseAmount,
            'allocations' => $this->allocationRows($allocations),
            'allocated_amount' => $allocated,
            'unallocated_amount' => (int) $payment->amount - $allocated,
            'base_allocated_amount' => $baseAllocated,
            'base_unallocated_amount' => $baseAmount - $baseAllocated,
            'currency_id' => $payment->currency_id,
            'transaction_id' => $payment->transaction_id,
            'formatted_created_at' => $payment->formattedCreatedAt,
            'formatted_payment_date' => $payment->formattedPaymentDate,
            'payment_pdf_url' => $payment->paymentPdfUrl,
            'customer' => $this->when(
                $payment->customer()->exists(),
                fn () => new CustomerResource($payment->customer)
            ),
            'payment_method' => $this->when(
                $payment->paymentMethod()->exists(),
                fn () => new PaymentMethodResource($payment->paymentMethod)
            ),
            'fields' => $this->when(
                $payment->printedFields()->exists(),
                fn () => CustomFieldValueResource::collection($payment->printedFields)
            ),
            'company' => $this->when(
                $payment->company()->exists(),
                fn () => new CompanyResource($payment->company)
            ),
            'currency' => $this->when(
                $payment->currency()->exists(),
                fn () => new CurrencyResource($payment->currency)
            ),
            'transaction' => $this->when(
                $payment->transaction()->exists(),
                fn () => new TransactionResource($payment->transaction)
            ),
        ];
    }

    /**
     * The payment's value in the company's currency.
     *
     * Recomputed from the amount and the rate when the column was never
     * written, with a falsy rate read as one so the payment keeps its face
     * value instead of reporting as worth nothing.
     */
    private function baseAmount(): int
    {
        $payment = $this->resource;

        if ($payment->base_amount === null) {
            return (int) round($payment->amount * ($payment->exchange_rate ?: 1));
        }

        return (int) $payment->base_amount;
    }

    /**
     * One row per invoice this payment has been allocated against.
     *
     * The invoice is nested in full where there is one; an allocation whose
     * invoice cannot be resolved still reports its amounts.
     */
    private function allocationRows(Collection $allocations): Collection
    {
        return $allocations->map(fn ($allocation) => [
            'id' => $allocation->id,
            'invoice_id' => $allocation->invoice_id,
            'amount' => $allocation->amount,
            'base_amount' => $allocation->base_amount,
            'invoice' => $allocation->invoice ? new InvoiceResource($allocation->invoice) : null,
        ]);
    }
}
