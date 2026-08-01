<?php

namespace App\Services\Document;

use App\Facades\Hashids;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Support\CreditNoteAmounts;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates credit notes (Stornorechnungen) that reverse an invoice in full or in
 * part, and keeps the reversed invoice's balance in step with them.
 *
 * A credit note is an invoice row with type = CREDIT_NOTE pointing back at the
 * invoice it reverses; each of its lines carries source_invoice_item_id, the
 * line of the original invoice it credits. Those links are what make partial
 * credits possible: they say how much of every line has already been credited,
 * which is the only input {@see CreditNoteAmounts} needs beyond the original
 * invoice's own stored figures.
 *
 * All arithmetic is delegated to that calculator, which works on the
 * cumulative-difference (telescoping) model, so crediting a line in chunks
 * always sums to exactly what crediting it in one go would have produced. This
 * service's job is the surrounding bookkeeping: locking the invoice, deciding
 * what "before" and "after" are, enforcing the domain invariants, negating the
 * magnitudes, and persisting the rows.
 */
class CreditNoteService
{
    public function __construct(
        private readonly DocumentItemService $documentItemService,
    ) {}

    /**
     * Create a credit note reversing the given invoice.
     *
     * @param  array  $items  lines to credit as [['id' => invoiceItemId, 'quantity' => float], ...].
     *                        An empty array credits every remaining quantity (a full reversal).
     * @param  string|null  $reason  free-text reason stored on the credit note
     *
     * @throws ValidationException
     */
    public function create(Invoice $invoice, array $items = [], ?string $reason = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $items, $reason) {
            // The invoice is re-read under a row lock because every guard below
            // is a read-then-write on it: two concurrent credit notes checking
            // the same remaining quantity would each be allowed and together
            // overdraw the invoice.
            $original = Invoice::query()
                ->whereKey($invoice->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $original->load(['items.taxes', 'taxes', 'fields', 'creditNotes.items']);

            $snapshot = $this->snapshot($original);
            $invoiced = $this->invoicedQuantities($original);
            $before = $this->creditedQuantities($original);
            $after = $this->targetQuantities($invoiced, $before, $items);

            $paid = (int) $original->payments()->sum('amount');
            $creditedBefore = $this->creditedTotal($original);

            $this->guard($original, $invoiced, $before, $after, $paid, $creditedBefore);

            $amounts = CreditNoteAmounts::forCredit($snapshot, $before, $after);

            if ($creditedBefore + $amounts['total'] > (int) $original->total - $paid) {
                throw ValidationException::withMessages([
                    'invoice' => ['credit_amount_exceeds_invoice_balance'],
                ]);
            }

            $creditNote = $this->persist($original, $amounts, $reason);

            $this->recalculateBalance($original);

            return Invoice::with([
                'items',
                'items.fields',
                'items.fields.customField',
                'customer',
                'taxes',
                'relatedInvoice',
            ])->find($creditNote->id);
        });
    }

    /**
     * How much of every line of the invoice is still creditable, in hundredths,
     * keyed by the original invoice_items.id.
     */
    public function remainingQuantities(Invoice $invoice): array
    {
        $invoice->loadMissing(['items', 'creditNotes.items']);

        $credited = $this->creditedQuantities($invoice);
        $remaining = [];

        foreach ($this->invoicedQuantities($invoice) as $itemId => $hundredths) {
            $remaining[$itemId] = max(0, $hundredths - ($credited[$itemId] ?? 0));
        }

        return $remaining;
    }

    /**
     * The amount already credited off this invoice, as a positive number of
     * cents (credit notes store negative totals).
     */
    public function creditedTotal(Invoice $invoice): int
    {
        return -(int) $invoice->creditNotes()->sum('total');
    }

    /**
     * Recompute the invoice's balance and status from what it was paid and what
     * has been credited off it.
     *
     * This deliberately does not live in {@see Invoice::getInvoiceStatusByAmount()}:
     * that method is called from the payment flow, and PaymentService::create()
     * adjusts the invoice BEFORE the Payment row is written, so a rule derived
     * from payments()->sum() would read a stale total there and settle the
     * invoice one payment short. This method only runs when a credit note is
     * created or deleted, where every payment and every credit note involved is
     * already persisted.
     */
    public function recalculateBalance(Invoice $invoice): void
    {
        $paid = (int) $invoice->payments()->sum('amount');
        $credited = $this->creditedTotal($invoice);
        $due = max(0, (int) $invoice->total - $paid - $credited);

        $invoice->due_amount = $due;
        $invoice->base_due_amount = (int) round($due * $invoice->exchange_rate);

        if ($due === 0) {
            // Nothing is owed any more, whether that came from money or from a
            // reversal, so the invoice must drop out of every "awaiting
            // payment" view. Which of the two settled it is carried by the
            // creditNotes relation, not by the status.
            $invoice->status = Invoice::STATUS_COMPLETED;
            $invoice->paid_status = Invoice::STATUS_PAID;
            $invoice->overdue = false;
        } else {
            $invoice->status = $invoice->getPreviousStatus();
            $invoice->paid_status = $paid > 0
                ? Invoice::STATUS_PARTIALLY_PAID
                : Invoice::STATUS_UNPAID;
        }

        $invoice->save();
    }

    /**
     * Enforce the credit-note invariants, in the order that produces the most
     * specific message for each situation.
     *
     * @throws ValidationException
     */
    protected function guard(Invoice $invoice, array $invoiced, array $before, array $after, int $paid, int $creditedBefore): void
    {
        $remaining = 0;

        foreach ($invoiced as $itemId => $hundredths) {
            $remaining += max(0, $hundredths - ($before[$itemId] ?? 0));
        }

        if ($remaining === 0 || (int) $invoice->total - $paid - $creditedBefore <= 0) {
            throw ValidationException::withMessages([
                'invoice' => ['invoice_already_fully_credited'],
            ]);
        }

        foreach ($after as $itemId => $hundredths) {
            if ($hundredths > ($invoiced[$itemId] ?? 0)) {
                throw ValidationException::withMessages([
                    'invoice' => ['credit_quantity_exceeds_remaining'],
                ]);
            }
        }

        foreach ($after as $itemId => $hundredths) {
            if ($hundredths > ($before[$itemId] ?? 0)) {
                return;
            }
        }

        throw ValidationException::withMessages([
            'invoice' => ['credit_note_must_credit_something'],
        ]);
    }

    /**
     * Write the credit-note document, its lines and its taxes.
     */
    protected function persist(Invoice $invoice, array $amounts, ?string $reason): Invoice
    {
        // A fresh SerialNumberService per document, as everywhere else in the
        // app: it is a stateful builder that keeps the number it computed, so a
        // shared instance would hand the same number to the next credit note.
        $serial = (new SerialNumberService)
            ->setModel(new Invoice)
            ->setCompany($invoice->company_id)
            ->setCustomer($invoice->customer_id)
            ->setSettingKey('credit_note_number_format')
            ->setSequenceScope(['type' => Invoice::TYPE_CREDIT_NOTE])
            ->setNextNumbers();

        // exchange_rate is a float multiplier, not a currency amount. The base_*
        // fields are pro-rated from the original's stored base_* integers by the
        // calculator, so they are negated as-is rather than recomputed through
        // the rate, which would re-round a decision already made.
        $creditNote = Invoice::create([
            'creator_id' => auth()->id(),
            'type' => Invoice::TYPE_CREDIT_NOTE,
            'related_invoice_id' => $invoice->id,
            'credit_reason' => $reason,
            'invoice_date' => Carbon::now()->format('Y-m-d'),
            // A reversal is never owed, so it has no due date at all. Leaving it
            // null also keeps the credit note out of every due/aging query.
            'due_date' => null,
            'invoice_number' => $serial->getNextNumber(),
            'sequence_number' => $serial->nextSequenceNumber,
            'customer_sequence_number' => $serial->nextCustomerSequenceNumber,
            'reference_number' => $invoice->invoice_number,
            'customer_id' => $invoice->customer_id,
            'company_id' => $invoice->company_id,
            'template_name' => $invoice->template_name,
            // A credit note gets the ordinary create-review-send lifecycle: born
            // DRAFT so the Send affordances appear, promoted to SENT by send().
            // Nothing is ever owed on it, so paid_status/due_amount below keep
            // it out of the payment flows regardless of status.
            'status' => Invoice::STATUS_DRAFT,
            // The credit note is born settled: it exists to pair with the
            // original invoice, nothing is ever owed on it, so it must never
            // surface as an open (negative) balance in any due/aging view.
            'paid_status' => Invoice::STATUS_PAID,
            'sub_total' => -$amounts['sub_total'],
            'discount' => $invoice->discount,
            'discount_type' => $invoice->discount_type,
            'discount_val' => -$amounts['discount_val'],
            'total' => -$amounts['total'],
            'due_amount' => 0,
            'tax_per_item' => $invoice->tax_per_item,
            'discount_per_item' => $invoice->discount_per_item,
            'tax' => -$amounts['tax'],
            'tax_included' => $invoice->tax_included,
            'notes' => $invoice->notes,
            'exchange_rate' => $invoice->exchange_rate,
            'base_discount_val' => -$amounts['base_discount_val'],
            'base_sub_total' => -$amounts['base_sub_total'],
            'base_total' => -$amounts['base_total'],
            'base_tax' => -$amounts['base_tax'],
            'base_due_amount' => 0,
            'currency_id' => $invoice->currency_id,
            'sales_tax_type' => $invoice->sales_tax_type,
            'sales_tax_address_type' => $invoice->sales_tax_address_type,
        ]);

        $creditNote->unique_hash = Hashids::connection(Invoice::class)->encode($creditNote->id);
        $creditNote->save();

        // recompute: false throughout. The calculator has already decided every
        // cent of this document, and re-deriving the line totals or the base_*
        // columns from price * quantity * rate would round a second time and
        // break the telescoping invariant by a cent.
        $this->documentItemService->createItems(
            $creditNote,
            $this->creditItems($invoice, $amounts['items']),
            recompute: false
        );

        if ($invoice->tax_per_item !== 'YES' && ! empty($amounts['taxes'])) {
            $this->documentItemService->createTaxes(
                $creditNote,
                $this->creditTaxes($invoice->taxes, $amounts['taxes']),
                recompute: false
            );
        }

        if ($invoice->fields()->exists()) {
            $customFields = [];

            foreach ($invoice->fields as $field) {
                $customFields[] = [
                    'id' => $field->custom_field_id,
                    'value' => $field->defaultAnswer,
                ];
            }

            $creditNote->addCustomFields($customFields);
        }

        return $creditNote;
    }

    /**
     * Build the credit-note line payloads: negated amounts from the calculator,
     * descriptive fields copied from the line each one credits.
     */
    protected function creditItems(Invoice $invoice, array $lines): array
    {
        $sourceItems = $invoice->items->keyBy('id');
        $items = [];

        foreach ($lines as $sourceId => $line) {
            /** @var InvoiceItem $source */
            $source = $sourceItems->get($sourceId);

            if (! $source) {
                continue;
            }

            $items[] = [
                'source_invoice_item_id' => $line['source_invoice_item_id'],
                'item_id' => $source->item_id,
                'name' => $source->name,
                'description' => $source->description,
                'unit_name' => $source->unit_name,
                'discount_type' => $source->discount_type,
                'discount' => $source->discount,
                // The quantity stays positive: what makes the line a credit is
                // the negative price and total, exactly as a full reversal does.
                'quantity' => $line['quantity'],
                'price' => -$line['price'],
                'base_price' => -$line['base_price'],
                'discount_val' => -$line['discount_val'],
                'tax' => -$line['tax'],
                'total' => -$line['total'],
                'base_discount_val' => -$line['base_discount_val'],
                'base_tax' => -$line['base_tax'],
                'base_total' => -$line['base_total'],
                'taxes' => $this->creditTaxes($source->taxes, $line['taxes']),
            ];
        }

        return $items;
    }

    /**
     * Build tax-row payloads: negated amounts from the calculator, descriptive
     * fields copied from the tax row each one reverses.
     */
    protected function creditTaxes(Collection $sourceTaxes, array $amounts): array
    {
        $byId = $sourceTaxes->keyBy('id');
        $taxes = [];

        foreach ($amounts as $taxId => $amount) {
            $source = $byId->get($taxId);

            if (! $source) {
                continue;
            }

            $taxes[] = [
                'tax_type_id' => $source->tax_type_id,
                'item_id' => $source->item_id,
                'name' => $source->name,
                'percent' => $source->percent,
                'compound_tax' => $source->compound_tax,
                'calculation_type' => $source->calculation_type,
                'fixed_amount' => $source->fixed_amount,
                'amount' => -$amount['amount'],
                'base_amount' => -$amount['base_amount'],
            ];
        }

        return $taxes;
    }

    /**
     * The original invoice's stored figures, in the shape the calculator reads.
     */
    protected function snapshot(Invoice $invoice): array
    {
        $items = [];

        foreach ($invoice->items as $item) {
            $items[$item->id] = [
                'price' => (int) $item->price,
                'quantity' => (float) $item->quantity,
                'discount_val' => (int) $item->discount_val,
                'tax' => (int) $item->tax,
                'total' => (int) $item->total,
                'base_price' => (int) $item->base_price,
                'base_discount_val' => (int) $item->base_discount_val,
                'base_tax' => (int) $item->base_tax,
                'base_total' => (int) $item->base_total,
                'taxes' => $this->snapshotTaxes($item->taxes),
            ];
        }

        return [
            'sub_total' => (int) $invoice->sub_total,
            'discount_val' => (int) $invoice->discount_val,
            'tax' => (int) $invoice->tax,
            'total' => (int) $invoice->total,
            'base_sub_total' => (int) $invoice->base_sub_total,
            'base_discount_val' => (int) $invoice->base_discount_val,
            'base_tax' => (int) $invoice->base_tax,
            'base_total' => (int) $invoice->base_total,
            'discount_per_item' => $invoice->discount_per_item,
            'tax_per_item' => $invoice->tax_per_item,
            'tax_included' => (bool) $invoice->tax_included,
            'items' => $items,
            'taxes' => $this->snapshotTaxes($invoice->taxes),
        ];
    }

    protected function snapshotTaxes(Collection $taxes): array
    {
        $snapshot = [];

        foreach ($taxes as $tax) {
            $snapshot[$tax->id] = [
                'amount' => (int) $tax->amount,
                'base_amount' => (int) $tax->base_amount,
            ];
        }

        return $snapshot;
    }

    /**
     * The invoiced quantity of every line, in hundredths.
     */
    protected function invoicedQuantities(Invoice $invoice): array
    {
        $quantities = [];

        foreach ($invoice->items as $item) {
            $quantities[$item->id] = CreditNoteAmounts::toHundredths($item->quantity);
        }

        return $quantities;
    }

    /**
     * The already-credited quantity of every line, in hundredths, read off the
     * credit notes that still exist. Deleting a credit note therefore gives its
     * quantities back without any separate bookkeeping.
     */
    protected function creditedQuantities(Invoice $invoice): array
    {
        $quantities = [];

        foreach ($invoice->creditNotes as $creditNote) {
            foreach ($creditNote->items as $item) {
                if (! $item->source_invoice_item_id) {
                    continue;
                }

                $quantities[$item->source_invoice_item_id] =
                    ($quantities[$item->source_invoice_item_id] ?? 0)
                    + CreditNoteAmounts::toHundredths($item->quantity);
            }
        }

        return $quantities;
    }

    /**
     * The cumulative credited quantities this credit note leaves behind: the
     * requested quantities on top of what was credited before, or every
     * invoiced quantity when nothing specific was requested (full reversal).
     */
    protected function targetQuantities(array $invoiced, array $before, array $items): array
    {
        if (empty($items)) {
            return $invoiced;
        }

        $after = $before;

        foreach ($items as $line) {
            $itemId = (int) $line['id'];

            $after[$itemId] = ($after[$itemId] ?? 0) + CreditNoteAmounts::toHundredths($line['quantity']);
        }

        return $after;
    }
}
