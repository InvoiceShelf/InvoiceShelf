<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('payment_allocations')) {
            Schema::create('payment_allocations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('payment_id')->index();
                $table->unsignedInteger('invoice_id')->index();
                $table->unsignedBigInteger('amount');
                $table->unsignedBigInteger('base_amount');
                $table->timestamps();

                $table->unique(['payment_id', 'invoice_id']);
            });
        }

        // The forensic record of every legacy link this migration refused to
        // turn into an allocation. Dropping payments.invoice_id destroys the
        // only trace of the association, and a payment recorded against a
        // draft invoice is a bookkeeping habit rather than a data fault: the
        // link is worth keeping so it can be restored once the invoice is
        // issued. Created on a fresh install too, so the repair command and
        // its queries never have to test for the table's existence.
        if (! Schema::hasTable('legacy_payment_links')) {
            Schema::create('legacy_payment_links', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('payment_id')->unique();
                $table->unsignedInteger('invoice_id');
                $table->string('reason');
                $table->timestamps();
            });
        }

        // A fresh install replays the historical payments migration before
        // reaching this migration. Existing v2/v3 databases arrive here with
        // the same nullable legacy column; a retry after a partial run does
        // not, and must not attempt the conversion again.
        if (! Schema::hasColumn('payments', 'invoice_id')) {
            $this->verifyLegacyAllocations();

            return;
        }

        DB::transaction(function (): void {
            $this->backfillLegacyAllocations();
            $this->verifyLegacyAllocations();
            $this->recalculateAffectedInvoices();
        });

        // Only what is actually there is dropped. A failed DDL statement cannot
        // be caught and stepped over on PostgreSQL: the migration runs in a
        // transaction there, and the first error aborts everything after it.
        // Manually upgraded databases may lack the constraint, the base schema
        // never indexed the column, and SQLite refuses to drop an indexed one.
        foreach ($this->foreignKeysOn('payments', 'invoice_id') as $foreignKey) {
            Schema::table('payments', function (Blueprint $table) use ($foreignKey) {
                // SQLite's foreign keys have no name and are dropped by column.
                $table->dropForeign($foreignKey['name'] ?? $foreignKey['columns']);
            });
        }

        foreach ($this->indexesOn('payments', 'invoice_id') as $index) {
            Schema::table('payments', function (Blueprint $table) use ($index) {
                $table->dropIndex($index);
            });
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('invoice_id');
        });
    }

    /**
     * The foreign keys on exactly this column.
     *
     * @return list<array{name: string|null, columns: list<string>}>
     */
    private function foreignKeysOn(string $table, string $column): array
    {
        return collect(Schema::getForeignKeys($table))
            ->filter(fn (array $foreignKey): bool => $foreignKey['columns'] === [$column])
            ->values()
            ->all();
    }

    /**
     * Names of the plain indexes on exactly this column.
     *
     * @return list<string>
     */
    private function indexesOn(string $table, string $column): array
    {
        return collect(Schema::getIndexes($table))
            ->filter(fn (array $index): bool => $index['columns'] === [$column] && ! $index['primary'])
            ->pluck('name')
            ->values()
            ->all();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('payment_allocations')) {
            Schema::dropIfExists('legacy_payment_links');

            return;
        }

        if (DB::table('payment_allocations')
            ->select('payment_id')
            ->groupBy('payment_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists()) {
            throw new RuntimeException('Cannot roll back payment allocations after a payment was allocated to multiple invoices.');
        }

        $hasPartialAllocation = DB::table('payment_allocations')
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->whereColumn('payment_allocations.amount', '!=', 'payments.amount')
            ->exists();

        if ($hasPartialAllocation) {
            throw new RuntimeException('Cannot roll back payment allocations with unapplied customer credit.');
        }

        if (! Schema::hasColumn('payments', 'invoice_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->unsignedInteger('invoice_id')->nullable()->index();
            });
        }

        DB::table('payment_allocations')->orderBy('id')->each(function (object $allocation): void {
            DB::table('payments')
                ->where('id', $allocation->payment_id)
                ->update(['invoice_id' => $allocation->invoice_id]);
        });

        Schema::dropIfExists('payment_allocations');
        // Dropped last, after the refusal guards above: a rollback that is
        // refused keeps the forensic link table, since the unapplied credit
        // it documents is exactly what caused the refusal.
        Schema::dropIfExists('legacy_payment_links');
    }

    private function backfillLegacyAllocations(): void
    {
        DB::table('payments')
            ->whereNotNull('invoice_id')
            ->orderBy('invoice_id')
            ->orderBy('payment_date')
            ->orderBy('id')
            ->each(function (object $payment): void {
                // A rerun after the allocation insert but before the legacy
                // column disappears leaves the already migrated payment alone.
                if (DB::table('payment_allocations')->where('payment_id', $payment->id)->exists()) {
                    return;
                }

                $invoice = DB::table('invoices')->where('id', $payment->invoice_id)->first();

                if (! $this->isValidLegacyLink($payment, $invoice)) {
                    Log::warning('Payment legacy invoice link was retained as unapplied customer credit.', [
                        'payment_id' => $payment->id,
                        'invoice_id' => $payment->invoice_id,
                    ]);

                    $this->recordLegacyLink($payment, $invoice);

                    return;
                }

                $allocated = (int) DB::table('payment_allocations')
                    ->where('invoice_id', $invoice->id)
                    ->sum('amount');
                $credited = $this->creditedTotal($invoice->id);
                $remaining = max(0, (int) $invoice->total - $credited - $allocated);
                $amount = min((int) $payment->amount, $remaining);

                if ($amount === 0) {
                    return;
                }

                DB::table('payment_allocations')->insert([
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $amount,
                    'base_amount' => $this->allocatedBaseAmount($payment, $amount),
                    // The legacy relationship existed at payment time; using
                    // its date preserves historical as-of statement balances
                    // instead of making every old allocation appear today.
                    'created_at' => $payment->payment_date,
                    'updated_at' => $payment->payment_date,
                ]);
            });
    }

    /**
     * Keep the declined association on file before the column carrying it is
     * dropped.
     *
     * A link is filed as 'draft' when the invoice is otherwise a perfectly
     * good target and the only objection is that it never left the draft
     * stage — the case `payments:restore-legacy-links` can put right once the
     * invoice is issued. Everything else is a 'mismatch': a missing invoice, a
     * credit note, or a target belonging to another company, contact or
     * currency. Those are never repaired automatically and are kept purely so
     * the operator can see what the association used to be.
     */
    private function recordLegacyLink(object $payment, ?object $invoice): void
    {
        // A rerun after the insert but before the legacy column disappears
        // must not file the link, or the note, a second time.
        if (DB::table('legacy_payment_links')->where('payment_id', $payment->id)->exists()) {
            return;
        }

        $isDraftLink = $this->isDraftLegacyLink($payment, $invoice);

        DB::table('legacy_payment_links')->insert([
            'payment_id' => $payment->id,
            'invoice_id' => $payment->invoice_id,
            'reason' => $isDraftLink ? 'draft' : 'mismatch',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($isDraftLink) {
            $this->annotateDraftPayment($payment, $invoice);
        }
    }

    /**
     * Whether the draft status is the sole reason the link was declined.
     */
    private function isDraftLegacyLink(object $payment, ?object $invoice): bool
    {
        return $invoice
            && (int) $payment->company_id === (int) $invoice->company_id
            && (int) $payment->customer_id === (int) $invoice->customer_id
            && (int) $payment->currency_id === (int) $invoice->currency_id
            && ($invoice->type ?? 'INVOICE') === 'INVOICE'
            && $invoice->status === 'DRAFT';
    }

    /**
     * Tell the story on the receipt itself.
     *
     * The forensic table is invisible to whoever opens the payment, so the
     * plain-text note is what explains why the money now sits as unapplied
     * credit. It is appended, never substituted for whatever was there.
     */
    private function annotateDraftPayment(object $payment, object $invoice): void
    {
        $note = sprintf(
            'Recorded against draft invoice %s before the 3.x upgrade; retained as unapplied customer credit.',
            $invoice->invoice_number
        );
        $existing = (string) ($payment->notes ?? '');

        DB::table('payments')->where('id', $payment->id)->update([
            'notes' => $existing === '' ? $note : $existing."\n".$note,
        ]);
    }

    private function isValidLegacyLink(object $payment, ?object $invoice): bool
    {
        return $invoice
            && (int) $payment->company_id === (int) $invoice->company_id
            && (int) $payment->customer_id === (int) $invoice->customer_id
            && (int) $payment->currency_id === (int) $invoice->currency_id
            && ($invoice->type ?? 'INVOICE') === 'INVOICE'
            && $invoice->status !== 'DRAFT';
    }

    private function allocatedBaseAmount(object $payment, int $amount): int
    {
        $paymentAmount = (int) $payment->amount;

        if ($paymentAmount === 0) {
            return 0;
        }

        $baseAmount = $payment->base_amount === null
            ? (int) round($paymentAmount * ((float) $payment->exchange_rate ?: 1))
            : (int) $payment->base_amount;

        return $this->proportionalAmount($baseAmount, $amount, $paymentAmount);
    }

    private function verifyLegacyAllocations(): void
    {
        $orphanedPayment = DB::table('payment_allocations')
            ->leftJoin('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->whereNull('payments.id')
            ->first();

        if ($orphanedPayment) {
            throw new RuntimeException('Payment allocation migration verification failed: allocation payment is missing.');
        }

        $overAllocatedPayment = DB::table('payment_allocations')
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->select('payments.id')
            ->groupBy('payments.id', 'payments.amount')
            ->havingRaw('SUM(payment_allocations.amount) > payments.amount')
            ->first();

        if ($overAllocatedPayment) {
            throw new RuntimeException('Payment allocation migration verification failed: an allocation exceeds its payment.');
        }

        $overAllocatedBasePayment = DB::table('payment_allocations')
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->select('payments.id', 'payments.amount', 'payments.base_amount', 'payments.exchange_rate')
            ->groupBy('payments.id', 'payments.amount', 'payments.base_amount', 'payments.exchange_rate')
            ->selectRaw('SUM(payment_allocations.base_amount) as allocated_base_amount')
            ->get()
            ->first(function (object $payment): bool {
                $baseAmount = $payment->base_amount === null
                    ? (int) round((int) $payment->amount * ((float) $payment->exchange_rate ?: 1))
                    : (int) $payment->base_amount;

                return (int) $payment->allocated_base_amount > $baseAmount;
            });

        if ($overAllocatedBasePayment) {
            throw new RuntimeException('Payment allocation migration verification failed: allocation base amount exceeds its payment.');
        }

        $invalidOwnership = DB::table('payment_allocations')
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->join('invoices', 'invoices.id', '=', 'payment_allocations.invoice_id')
            ->where(function ($query): void {
                $query->whereColumn('payments.company_id', '!=', 'invoices.company_id')
                    ->orWhereColumn('payments.customer_id', '!=', 'invoices.customer_id')
                    ->orWhereColumn('payments.currency_id', '!=', 'invoices.currency_id');
            })
            ->first();

        if ($invalidOwnership) {
            throw new RuntimeException('Payment allocation migration verification failed: allocation ownership mismatch.');
        }

        $invalidTarget = DB::table('payment_allocations')
            ->leftJoin('invoices', 'invoices.id', '=', 'payment_allocations.invoice_id')
            ->where(function ($query): void {
                $query->whereNull('invoices.id')
                    ->orWhere('payment_allocations.amount', '<=', 0)
                    ->orWhere('payment_allocations.base_amount', '<', 0)
                    ->orWhere('invoices.type', '!=', 'INVOICE')
                    ->orWhere('invoices.status', 'DRAFT');
            })
            ->first();

        if ($invalidTarget) {
            throw new RuntimeException('Payment allocation migration verification failed: allocation target is not payable.');
        }

        DB::table('payment_allocations')
            ->select('invoice_id')
            ->groupBy('invoice_id')
            ->orderBy('invoice_id')
            ->each(function (object $allocation): void {
                $invoice = DB::table('invoices')->where('id', $allocation->invoice_id)->first();
                $allocated = (int) DB::table('payment_allocations')->where('invoice_id', $invoice->id)->sum('amount');

                if ($allocated > max(0, (int) $invoice->total - $this->creditedTotal($invoice->id))) {
                    throw new RuntimeException('Payment allocation migration verification failed: allocation exceeds invoice balance.');
                }
            });
    }

    private function recalculateAffectedInvoices(): void
    {
        // Include all legacy targets, not only allocations that survived the
        // conversion. An invalid link or excess legacy payment may previously
        // have driven a target invoice's stored balance below its true balance.
        DB::table('invoices')
            ->where('type', 'INVOICE')
            ->whereIn('id', function ($query): void {
                $query->select('invoice_id')->from('payments')->whereNotNull('invoice_id');
            })
            ->orderBy('id')
            ->each(function (object $invoice): void {
                $paid = (int) DB::table('payment_allocations')->where('invoice_id', $invoice->id)->sum('amount');
                $due = max(0, (int) $invoice->total - $this->creditedTotal($invoice->id) - $paid);

                DB::table('invoices')->where('id', $invoice->id)->update([
                    'due_amount' => $due,
                    'base_due_amount' => (int) round($due * (float) $invoice->exchange_rate),
                    'status' => $due === 0
                        ? 'COMPLETED'
                        : ((bool) $invoice->viewed ? 'VIEWED' : ((bool) $invoice->sent ? 'SENT' : 'DRAFT')),
                    'paid_status' => $due === 0 ? 'PAID' : ($paid > 0 ? 'PARTIALLY_PAID' : 'UNPAID'),
                ]);
            });
    }

    private function creditedTotal(int $invoiceId): int
    {
        if (! Schema::hasColumn('invoices', 'related_invoice_id')) {
            return 0;
        }

        return -(int) DB::table('invoices')
            ->where('related_invoice_id', $invoiceId)
            ->where('type', 'CREDIT_NOTE')
            ->sum('total');
    }

    /**
     * Calculate floor(baseAmount * allocationAmount / paymentAmount) without
     * multiplying two arbitrary BIGINT values. The long-division remainder
     * loop keeps every intermediate value below paymentAmount.
     */
    private function proportionalAmount(int $baseAmount, int $allocationAmount, int $paymentAmount): int
    {
        $whole = intdiv($baseAmount, $paymentAmount) * $allocationAmount;
        $remainder = $baseAmount % $paymentAmount;
        $quotient = 0;
        $modulo = 0;
        $factor = $remainder;
        $multiplier = $allocationAmount;

        while ($multiplier > 0) {
            if ($multiplier % 2 === 1) {
                if ($modulo >= $paymentAmount - $factor) {
                    $quotient++;
                    $modulo -= $paymentAmount - $factor;
                } else {
                    $modulo += $factor;
                }
            }

            if ($factor >= $paymentAmount - $factor) {
                $factor -= $paymentAmount - $factor;
            } else {
                $factor += $factor;
            }

            $multiplier = intdiv($multiplier, 2);
        }

        return $whole + $quotient;
    }
};
