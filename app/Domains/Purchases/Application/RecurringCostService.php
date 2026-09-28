<?php

namespace App\Domains\Purchases\Application;

use App\Domains\Purchases\Contracts\RecurringCostNotifier;
use App\Domains\Purchases\Http\Requests\BillRequest;
use App\Domains\Purchases\Http\Requests\RecurringCostRequest;
use App\Domains\Purchases\Models\RecurringCost;
use App\Domains\Taxation\Models\TaxType;
use App\Support\Recurrence\Cadence;
use App\Support\Recurrence\RecurrenceRunner;
use App\Support\Recurrence\RecurringSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Recurring bills and recurring paid expenses: their schedules, and what the
 * recurrence runner generates from them.
 *
 * A schedule stores a template, a bill without its dates or an expense
 * without its date, and each run turns it into a record through the same
 * services the forms use, so a run validates exactly as a person's entry
 * would. A template that has gone stale (a deleted tax, a disabled supplier)
 * fails that run with a reason on the schedule instead of writing anything.
 */
class RecurringCostService
{
    public function __construct(
        private readonly PurchaseDocumentService $documents,
        private readonly ExpenseService $expenses,
        private readonly PurchaseCustomFields $customFields,
        private readonly RecurrenceRunner $runner,
        private readonly RecurringCostNotifier $notifier,
    ) {}

    /**
     * Create a schedule, or change one.
     *
     * Once a schedule has generated anything its supplier and mode are fixed:
     * a different supplier or kind of record is a different schedule. A
     * change of frequency or start moves the next run; a completed schedule
     * whose limit no longer holds is active again.
     */
    public function save(?RecurringCost $schedule, int $companyId, ?int $actorId, array $data): RecurringCost
    {
        $validator = Validator::make($data, RecurringCostRequest::rulesFor($companyId));
        $validator->excludeUnvalidatedArrayKeys = false;
        $data = $validator->validate();

        return DB::transaction(function () use ($schedule, $companyId, $actorId, $data): RecurringCost {
            $record = $schedule
                ? RecurringCost::query()->forCompany($companyId)->lockForUpdate()->findOrFail($schedule->id)
                : new RecurringCost(['company_id' => $companyId, 'creator_id' => $actorId]);

            if ($record->exists && $record->generatedCount() > 0) {
                PurchaseInputs::ensure($data['supplier_id'] == $record->supplier_id, 'supplier_id', 'purchase_recurring_supplier_locked');
                PurchaseInputs::ensure($data['mode'] === $record->mode, 'mode', 'purchase_recurring_supplier_locked');
            }

            $existingAnswers = $record->mode === RecurringCost::MODE_BILL ? ($record->template['customFields'] ?? []) : [];
            $template = $this->validateTemplate($companyId, $data, $existingAnswers);
            $cadenceChanged = ! $record->exists
                || $record->frequency !== $data['frequency']
                || substr((string) $record->starts_at, 0, 10) !== $data['starts_at'];

            $record->fill([
                ...Arr::except($data, ['template', 'status']),
                'template' => $template,
                'due_days' => $data['due_days'] ?? 0,
                'create_as_draft' => $data['mode'] === RecurringCost::MODE_BILL && ($data['create_as_draft'] ?? false),
                'notify_creator' => $data['notify_creator'] ?? false,
            ]);

            if (! $record->exists) {
                $record->status = $data['status'] ?? RecurringSchedule::ACTIVE;
            } elseif (isset($data['status']) && $record->status !== RecurringSchedule::COMPLETED) {
                $record->status = $data['status'];
            }

            if ($cadenceChanged || $record->next_run_at === null) {
                $record->next_run_at = $this->firstRun($record)->format('Y-m-d H:i:s');
            }

            $this->settleStatus($record);
            $record->save();

            return $record;
        });
    }

    /**
     * Pause a schedule, or resume one. A resumed schedule skips the runs that
     * fell while it was paused, but keeps today's if it has not been made.
     */
    public function act(RecurringCost $schedule, string $action): RecurringCost
    {
        return DB::transaction(function () use ($schedule, $action): RecurringCost {
            $record = RecurringCost::query()->whereKey($schedule->id)->lockForUpdate()->firstOrFail();

            if ($action === 'pause') {
                PurchaseInputs::ensure($record->status === RecurringSchedule::ACTIVE, 'action', 'purchase_recurring_not_active');
                $record->status = RecurringSchedule::ON_HOLD;
            } else {
                PurchaseInputs::ensure($record->status === RecurringSchedule::ON_HOLD, 'action', 'purchase_recurring_not_paused');
                $record->status = RecurringSchedule::ACTIVE;

                $startOfToday = CarbonImmutable::now($record->scheduleTimeZone())->startOfDay();

                if ($record->next_run_at === null || CarbonImmutable::parse($record->next_run_at)->lessThan($startOfToday)) {
                    $record->next_run_at = Cadence::next($record->frequency, $startOfToday->subSecond(), $record->scheduleTimeZone())
                        ->format('Y-m-d H:i:s');
                }

                $this->settleStatus($record);
            }

            $record->save();

            return $record;
        });
    }

    /**
     * Generate every bill and expense the active schedules have fallen due
     * for, catching up missed runs.
     *
     * @return int the records generated
     */
    public function generateDue(): int
    {
        return $this->runner->run(
            RecurringCost::query(),
            fn (RecurringCost $schedule, string $date) => $this->generate($schedule, $date),
            fn (RecurringCost $schedule, Throwable $error) => $this->failed($schedule, $error),
        );
    }

    /**
     * The record for one run, inside the runner's transaction. A run already
     * made for that day is not made again.
     */
    private function generate(RecurringCost $schedule, string $date): void
    {
        $exists = $schedule->occurrences()->where('scheduled_for', $date)->exists();

        if (! $exists) {
            $template = $this->validateTemplate($schedule->company_id, [...$schedule->toArray(), 'starts_at' => $date], [], true);
            $record = $schedule->mode === RecurringCost::MODE_BILL
                ? $this->generateBill($schedule, $template, $date)
                : $this->generateExpense($schedule, $template, $date);

            $schedule->occurrences()->create([
                'company_id' => $schedule->company_id,
                'scheduled_for' => $date,
                'record_type' => $record->getMorphClass(),
                'record_id' => $record->getKey(),
            ]);

            if ($schedule->notify_creator) {
                DB::afterCommit(fn () => $this->notifier->generated($schedule, $record));
            }
        }

        $schedule->last_error = null;
    }

    private function generateBill(RecurringCost $schedule, array $template, string $date): Model
    {
        return $this->documents->saveBill(null, $schedule->company_id, $schedule->creator_id, [
            ...$template,
            'supplier_id' => $schedule->supplier_id,
            'document_date' => $date,
            'due_date' => CarbonImmutable::parse($date)->addDays($schedule->due_days)->toDateString(),
            'status' => $schedule->create_as_draft ? 'DRAFT' : 'OPEN',
        ]);
    }

    private function generateExpense(RecurringCost $schedule, array $template, string $date): Model
    {
        $supplier = PurchaseInputs::lockSupplier($schedule->company_id, $schedule->supplier_id);
        PurchaseInputs::ensure($supplier->enabled, 'supplier_id', 'purchase_supplier_inactive');

        $money = PurchaseInputs::money($schedule->company_id, $template);

        return $this->expenses->create([
            ...Arr::except($template, ['taxes']),
            ...$money,
            'company_id' => $schedule->company_id,
            'creator_id' => $schedule->creator_id,
            'supplier_id' => $schedule->supplier_id,
            'expense_date' => $date,
            'base_amount' => PurchaseInputs::base($template['amount'], $money['exchange_rate']),
        ], $template['taxes'] ?? []);
    }

    /**
     * Note why a run failed on the schedule, and tell its creator when a run
     * of failures begins; the runner tries again later.
     */
    private function failed(RecurringCost $schedule, Throwable $error): void
    {
        $reason = $error instanceof ValidationException
            ? (string) (Arr::flatten($error->errors())[0] ?? 'purchase_recurring_failed')
            : 'purchase_recurring_failed';

        if (! $error instanceof ValidationException) {
            report($error);
        }

        $firstFailure = $schedule->last_error === null;
        RecurringCost::query()->whereKey($schedule->id)->update(['last_error' => $reason]);

        if ($firstFailure && $schedule->notify_creator) {
            $this->notifier->failed($schedule, $reason);
        }
    }

    /**
     * The first run on or after the start date, or after now when the start
     * is past: a schedule never generates for days before it was set up.
     */
    private function firstRun(RecurringCost $record): CarbonImmutable
    {
        $timezone = $record->scheduleTimeZone();
        $start = CarbonImmutable::parse($record->starts_at, $timezone)->startOfDay();
        $today = CarbonImmutable::now($timezone)->startOfDay();

        return Cadence::next($record->frequency, ($start->greaterThan($today) ? $start : $today)->subSecond(), $timezone);
    }

    /**
     * Complete a schedule whose limit leaves no more runs, and reactivate a
     * completed one whose limit was extended.
     */
    private function settleStatus(RecurringCost $record): void
    {
        $nextDate = Cadence::localDate($record->next_run_at, $record->scheduleTimeZone());
        $finished = RecurrenceRunner::limitReached($record, $nextDate);

        if ($finished && $record->status === RecurringSchedule::ACTIVE) {
            $record->status = RecurringSchedule::COMPLETED;
        } elseif (! $finished && $record->status === RecurringSchedule::COMPLETED) {
            $record->status = RecurringSchedule::ACTIVE;
        }
    }

    /**
     * The template as the record's own form would accept it.
     *
     * A bill template is checked with the bill rules (the dates filled in for
     * the check and taken out again), carrying its custom field answers; when
     * generating, answers to fields deleted since are dropped. An expense
     * template has its own rules and no custom fields.
     */
    private function validateTemplate(int $companyId, array $data, array $existing = [], bool $generating = false): array
    {
        $input = $data['template'];

        if ($data['mode'] === RecurringCost::MODE_BILL) {
            if ($generating) {
                $input['customFields'] = $this->customFields->retained($companyId, 'Bill', $input['customFields'] ?? []);
            }

            $input['customFields'] = $this->customFields->resolve(
                $companyId,
                'Bill',
                $input['customFields'] ?? [],
                $this->customFields->retained($companyId, 'Bill', $existing),
                'template.customFields',
            );

            $validated = Validator::make([
                ...$input,
                'supplier_id' => $data['supplier_id'],
                'document_date' => $data['starts_at'],
                'due_date' => $data['starts_at'],
                'status' => 'OPEN',
            ], BillRequest::rulesFor($companyId))->validate();

            return Arr::except($validated, ['supplier_id', 'document_date', 'due_date', 'status']);
        }

        $validated = Validator::make($input, [
            'customFields' => ['prohibited'],
            'amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')],
            'exchange_rate' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'expense_category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')->where('company_id', $companyId)],
            'payment_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'id')->where('company_id', $companyId)],
            'notes' => ['nullable', 'string', 'max:10000'],
            'taxes' => ['sometimes', 'array'],
            'taxes.*.tax_type_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('tax_types', 'id')
                    ->where('company_id', $companyId)
                    ->where('type', TaxType::TYPE_GENERAL)
                    ->where('transaction_type', TaxType::TRANSACTION_TYPE_PURCHASES),
            ],
            'taxes.*.amount' => ['required', 'integer', 'min:0'],
        ])->validate();

        PurchaseInputs::ensure(
            array_sum(array_column($validated['taxes'] ?? [], 'amount')) <= $validated['amount'],
            'template.taxes',
            'purchase_recurring_tax_exceeds_amount',
        );

        return $validated;
    }
}
