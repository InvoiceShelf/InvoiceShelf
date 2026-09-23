<?php

namespace App\Platform\Mcp\Presenters;

use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use Illuminate\Database\Eloquent\Model;

/**
 * Invoices, credit notes and estimates. Amounts are in the document's own
 * currency; `exchange_rate` turns them into the company's.
 */
final class DocumentPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function invoiceSummary(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->invoice_number,
            'kind' => $invoice->isCreditNote() ? 'credit_note' : 'invoice',
            'customer' => ['id' => $invoice->customer_id, 'name' => $invoice->customer?->name],
            'date' => Date::of($invoice, 'invoice_date'),
            'due_date' => Date::of($invoice, 'due_date'),
            'status' => $invoice->status,
            'paid_status' => $invoice->paid_status,
            'total' => Money::of($invoice->total, $invoice->currency_id),
            'due' => Money::of($invoice->due_amount, $invoice->currency_id),
            'app_url' => Link::to("invoices/{$invoice->id}/view"),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function invoiceDetail(Invoice $invoice): array
    {
        $invoice->loadMissing(['items.taxes', 'taxes', 'allocations.payment', 'creditNotes', 'relatedInvoice']);

        return self::invoiceSummary($invoice) + self::body($invoice) + [
            'reference_number' => $invoice->reference_number,
            'sent' => (bool) $invoice->sent,
            'viewed' => (bool) $invoice->viewed,
            'payments' => $invoice->allocations
                ->map(fn ($allocation) => [
                    'payment_id' => $allocation->payment_id,
                    'number' => $allocation->payment?->payment_number,
                    'date' => $allocation->payment ? Date::of($allocation->payment, 'payment_date') : null,
                    'amount' => Money::of($allocation->amount, $invoice->currency_id),
                ])
                ->values()
                ->all(),
            'credit_notes' => $invoice->creditNotes
                ->map(fn (Invoice $note) => [
                    'id' => $note->id,
                    'number' => $note->invoice_number,
                    'total' => Money::of($note->total, $note->currency_id),
                ])
                ->values()
                ->all(),
            'credits_invoice' => $invoice->relatedInvoice
                ? ['id' => $invoice->relatedInvoice->id, 'number' => $invoice->relatedInvoice->invoice_number]
                : null,
            'credit_reason' => $invoice->credit_reason,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function estimateSummary(Estimate $estimate): array
    {
        return [
            'id' => $estimate->id,
            'number' => $estimate->estimate_number,
            'customer' => ['id' => $estimate->customer_id, 'name' => $estimate->customer?->name],
            'date' => Date::of($estimate, 'estimate_date'),
            'expiry_date' => Date::of($estimate, 'expiry_date'),
            'status' => $estimate->status,
            'total' => Money::of($estimate->total, $estimate->currency_id),
            'app_url' => Link::to("estimates/{$estimate->id}/view"),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function estimateDetail(Estimate $estimate): array
    {
        $estimate->loadMissing(['items.taxes', 'taxes']);

        return self::estimateSummary($estimate) + self::body($estimate) + [
            'reference_number' => $estimate->reference_number,
        ];
    }

    /**
     * What invoices and estimates share: lines, discount, taxes and sums.
     *
     * @return array<string, mixed>
     */
    private static function body(Invoice|Estimate $document): array
    {
        $currency = $document->currency_id;

        return [
            'currency' => $document->currency?->code,
            'exchange_rate' => (float) $document->exchange_rate,
            'prices_include_tax' => (bool) $document->tax_included,
            'lines' => $document->items
                ->map(fn (Model $line) => [
                    'item_id' => $line->item_id,
                    'name' => $line->name,
                    'description' => Text::plain($line->description),
                    'quantity' => $line->quantity + 0,
                    'unit' => $line->unit_name,
                    'unit_price' => Money::of($line->price, $currency),
                    'discount' => (float) $line->discount_val !== 0.0 ? [
                        'type' => $line->discount_type,
                        'value' => $line->discount_type === 'fixed' ? Money::amount((int) $line->discount) : (string) ($line->discount + 0),
                        'amount' => Money::of($line->discount_val, $currency),
                    ] : null,
                    'taxes' => self::taxes($line->taxes, $currency),
                    'total' => Money::of($line->total, $currency),
                    'custom_fields' => CustomFieldAnswers::of($line),
                ])
                ->values()
                ->all(),
            'sub_total' => Money::of($document->sub_total, $currency),
            'discount' => (int) $document->discount_val !== 0 ? [
                'type' => $document->discount_type,
                'value' => $document->discount_type === 'fixed' ? Money::amount((int) $document->discount) : (string) ($document->discount + 0),
                'amount' => Money::of($document->discount_val, $currency),
            ] : null,
            'taxes' => self::taxes($document->taxes, $currency),
            'tax' => Money::of($document->tax, $currency),
            'notes' => Text::plain($document->notes),
            'template' => $document->template_name,
            'custom_fields' => CustomFieldAnswers::of($document),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function taxes(iterable $taxes, int|string|null $currency): array
    {
        $rows = [];

        foreach ($taxes as $tax) {
            $rows[] = [
                'tax_type_id' => (int) $tax->tax_type_id,
                'name' => $tax->name,
                'percent' => $tax->calculation_type === 'fixed' ? null : (float) $tax->percent,
                'compound' => (bool) $tax->compound_tax,
                'amount' => Money::of($tax->amount, $currency),
            ];
        }

        return $rows;
    }
}
