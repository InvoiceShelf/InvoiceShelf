<?php

namespace App\Domains\Sales\Http\Requests;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Metadata\Http\Requests\Concerns\ValidatesCustomFields;
use App\Domains\Sales\Models\RecurringInvoice;
use App\Support\DocumentTotals;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Vets a standing order and reshapes it into the row the service stores.
 *
 * A schedule carries a whole invoice in template form, so what arrives is a
 * document's payload with three extra fields bolted on: the cron expression
 * that decides when it fires, the date it starts running, and the limit that
 * eventually retires it. Everything else — items, discounts, taxes — is
 * checked and recomputed exactly as it is on a real invoice.
 */
class RecurringInvoiceRequest extends FormRequest
{
    use Concerns\ValidatesDocumentTaxPlaceholders;
    use ValidatesCustomFields;

    /**
     * Every caller is let through; the controller holds the gate.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Rules for the schedule and for the invoice template it carries.
     *
     * The cron expression, the start date and the status are checked for
     * presence only: a string the cron parser cannot read gets no validation
     * message and instead surfaces as an error when the first firing is worked
     * out. The limit fields answer to the chosen limit mode — a count is
     * demanded for COUNT, an end date for DATE, and neither for NONE.
     */
    public function rules(): array
    {
        $homeCurrency = CompanySetting::getSetting('currency', $this->header('company'));

        $rules = [
            'starts_at' => [
                'required',
            ],
            'send_automatically' => [
                'required',
                'boolean',
            ],
            'customer_id' => [
                'required',
            ],
            'exchange_rate' => [
                'nullable',
            ],
            'discount' => [
                'numeric',
                'required',
            ],
            'discount_val' => [
                'integer',
                'required',
            ],
            'sub_total' => [
                'integer',
                'required',
            ],
            'total' => [
                'integer',
                'max:999999999999',
                'required',
            ],
            'tax' => [
                'required',
            ],
            'status' => [
                'required',
            ],
            'frequency' => [
                'required',
            ],
            'limit_by' => [
                'required',
            ],
            'limit_count' => [
                'required_if:limit_by,COUNT',
            ],
            'limit_date' => [
                'required_if:limit_by,DATE',
            ],
            'items' => [
                'required',
            ],
            'items.*' => [
                'required',
            ],
            'items.*.description' => [
                'nullable',
            ],
        ];

        // A contact billed in some other currency than the company's turns the
        // otherwise optional rate into a hard requirement. The contact is
        // looked up by bare id, so one belonging to another company answers
        // here just the same.
        $contact = Customer::find($this->customer_id);

        if ($contact && $homeCurrency && (string) $contact->currency_id !== $homeCurrency) {
            $rules['exchange_rate'] = [
                'required',
            ];
        }

        return array_merge(
            $rules,
            $this->customFieldRules(),
            $this->customFieldRules('items.*.custom_fields'),
        );
    }

    /**
     * Reject any per-item tax row that carries an amount without a type.
     */
    public function withValidator(Validator $validator): void
    {
        $this->validateDocumentTaxPlaceholders($validator);
    }

    /**
     * Fold the submission into the columns of the schedule row.
     *
     * The submitted sub-total, tax and grand total are thrown away and worked
     * out again from the line items, because every invoice this schedule mints
     * inherits them. The stored currency is always the contact's; the
     * submitted currency id only decides whether an exchange rate is carried
     * or pinned at one.
     */
    public function getRecurringInvoicePayload()
    {
        $company = $this->header('company');

        $companyCurrency = CompanySetting::getSetting('currency', $company);
        $submittedCurrency = $this->currency_id;
        $rate = $companyCurrency != $submittedCurrency ? $this->exchange_rate : 1;
        $contactCurrency = Customer::find($this->customer_id)->currency_id;

        $nextRun = RecurringInvoice::getNextInvoiceDate($this->frequency, $this->starts_at);

        $perItemTax = CompanySetting::getSetting('tax_per_item', $company) ?? 'NO ';
        $perItemDiscount = CompanySetting::getSetting('discount_per_item', $company) ?? 'NO';

        $totals = DocumentTotals::compute(
            $this->items ?? [],
            $this->taxes ?? [],
            $this->discount_val,
            $perItemTax,
            (bool) $this->tax_included,
            $perItemDiscount
        );

        $submitted = collect($this->withoutCustomFields($this->except('items', 'taxes')));

        return $submitted
            ->merge([
                'creator_id' => $this->user()->id,
                'company_id' => $company,
                'next_invoice_at' => $nextRun,
                'tax_per_item' => $perItemTax,
                'discount_per_item' => $perItemDiscount,
                'sub_total' => $totals['sub_total'],
                'total' => $totals['total'],
                'tax' => $totals['tax'],
                'due_amount' => $totals['total'],
                'exchange_rate' => $rate,
                'base_sub_total' => $totals['sub_total'] * $rate,
                'base_total' => $totals['total'] * $rate,
                'base_tax' => $totals['tax'] * $rate,
                'currency_id' => $contactCurrency,
            ])
            ->toArray();
    }
}
