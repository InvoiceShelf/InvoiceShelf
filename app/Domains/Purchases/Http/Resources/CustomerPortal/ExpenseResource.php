<?php

namespace App\Domains\Purchases\Http\Resources\CustomerPortal;

use App\Domains\Accounts\Http\Resources\CustomerPortal\CompanyResource;
use App\Domains\Contacts\Http\Resources\CustomerPortal\CustomerResource;
use App\Domains\Metadata\Http\Resources\CustomerPortal\CustomFieldValueResource;
use App\Domains\Money\Http\Resources\CustomerPortal\CurrencyResource;
use App\Domains\Receivables\Http\Resources\CustomerPortal\PaymentMethodResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A money-out record as the customer portal publishes it.
 *
 * Two things are held back next to the admin payload: the staff account that
 * recorded the spend -- neither its id nor the account itself -- and the
 * purchase taxes raised against it. Everything else matches, receipt included:
 * a link with the file's kind, its path on disk, and the media row behind it,
 * each of which asks the media library for the same first file, so three
 * lookups a row whether or not anything is attached. The two formatted dates
 * likewise read the owning company's date format as they go.
 *
 * The associations underneath are each gated on an existence probe run against
 * the database, so they are correct however the caller loaded the row, and
 * cost a query apiece per serialised row plus a second to fetch the record the
 * probe just confirmed. Kept as the payload's established shape.
 */
class ExpenseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        $expense = $this->resource;

        return [
            'id' => $expense->id,
            'expense_date' => $expense->expense_date,
            'expense_number' => $expense->expense_number,
            'amount' => $expense->amount,
            'notes' => $expense->notes,
            'customer_id' => $expense->customer_id,
            'attachment_receipt_url' => $expense->receipt_url,
            'attachment_receipt' => $expense->receipt,
            'attachment_receipt_meta' => $expense->receipt_meta,
            'company_id' => $expense->company_id,
            'expense_category_id' => $expense->expense_category_id,
            'formatted_expense_date' => $expense->formattedExpenseDate,
            'formatted_created_at' => $expense->formattedCreatedAt,
            'exchange_rate' => $expense->exchange_rate,
            'currency_id' => $expense->currency_id,
            'base_amount' => $expense->base_amount,
            'payment_method_id' => $expense->payment_method_id,
            'customer' => $this->when(
                $expense->customer()->exists(),
                fn () => new CustomerResource($expense->customer)
            ),
            'expense_category' => $this->when(
                $expense->category()->exists(),
                fn () => new ExpenseCategoryResource($expense->category)
            ),
            'fields' => $this->when(
                $expense->printedFields()->exists(),
                fn () => CustomFieldValueResource::collection($expense->printedFields)
            ),
            'company' => $this->when(
                $expense->company()->exists(),
                fn () => new CompanyResource($expense->company)
            ),
            'currency' => $this->when(
                $expense->currency()->exists(),
                fn () => new CurrencyResource($expense->currency)
            ),
            'payment_method' => $this->when(
                $expense->paymentMethod()->exists(),
                fn () => new PaymentMethodResource($expense->paymentMethod)
            ),
        ];
    }
}
