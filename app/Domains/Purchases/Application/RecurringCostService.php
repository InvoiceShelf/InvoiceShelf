<?php

namespace App\Domains\Purchases\Application;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Purchases\Http\Requests\BillRequest;
use App\Domains\Purchases\Http\Requests\RecurringCostRequest;
use App\Domains\Purchases\Models\RecurringCost;
use App\Domains\Purchases\Models\RecurringCostOccurrence;
use App\Domains\Taxation\Models\TaxType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class RecurringCostService
{
    public function __construct(private readonly PurchaseDocumentService $documents, private readonly ExpenseService $expenses, private readonly PurchaseCustomFields $customFields) {}

    public function save(?RecurringCost $schedule, int $companyId, ?int $actorId, array $data): RecurringCost
    {
        $validator = Validator::make($data, RecurringCostRequest::rulesFor($companyId));
        $validator->excludeUnvalidatedArrayKeys = false;
        $data = $validator->validate();
        PurchaseInputs::ensure($data['mode'] !== 'EXPENSE' || $data['auto_record_paid'], 'auto_record_paid', 'Explicitly enable automatic paid-expense recording.');

        return DB::transaction(function () use ($schedule, $companyId, $actorId, $data): RecurringCost {
            // Scheduler locks this row first too; it may subsequently lock the supplier.
            $record = $schedule ? RecurringCost::query()->forCompany($companyId)->lockForUpdate()->findOrFail($schedule->id) : new RecurringCost(['company_id' => $companyId, 'creator_id' => $actorId]);
            if ($schedule && $record->occurrence_count > 0) {
                foreach (['supplier_id', 'mode', 'starts_at', 'frequency', 'interval'] as $key) {
                    PurchaseInputs::ensure($data[$key] == $record->$key, $key, 'Create a new schedule to change its cadence or supplier after generation.');
                }
            }
            $template = $this->validateTemplate($companyId, $data, $record->mode === 'BILL' ? ($record->template['customFields'] ?? []) : []);
            $record->fill([...$data, 'template' => $template, 'timezone' => CompanySetting::getSetting('time_zone', $companyId) ?: config('app.timezone', 'UTC')]);
            if (! $record->exists || $record->occurrence_count === 0) {
                $record->next_run_at = $data['starts_at'];
            }
            if (! $record->exists) {
                $record->status = 'ACTIVE';
            }
            $record->save();
            PurchaseAudit::record($record, $schedule ? 'updated' : 'created', $actorId);

            return $record;
        });
    }

    public function act(RecurringCost $schedule, string $action, ?string $reason, ?int $actorId): void
    {
        DB::transaction(function () use ($schedule, $action, $actorId): void {
            $record = RecurringCost::query()->whereKey($schedule->id)->lockForUpdate()->firstOrFail();
            PurchaseInputs::ensure(in_array($action, ['pause', 'resume'], true), 'action', 'Use pause or resume for recurring costs.');
            if ($action === 'pause') {
                PurchaseInputs::ensure($record->status === 'ACTIVE', 'action', 'Only an active schedule can be paused.');
                $record->update(['status' => 'PAUSED']);
            } else {
                PurchaseInputs::ensure($record->status === 'PAUSED', 'action', 'Only a paused schedule can be resumed.');
                $today = CarbonImmutable::now($record->timezone)->toDateString();
                $next = $record->next_run_at ?? $record->starts_at;
                while ($next <= $today) {
                    $next = $this->nextDate($record, $next);
                }
                $record->update(['status' => $this->finished($record, $next) ? 'COMPLETED' : 'ACTIVE', 'next_run_at' => $next]);
            }
            PurchaseAudit::record($record, $action, $actorId);
        });
    }

    /** Catch up a bounded batch; each occurrence and its next date commit together. */
    public function generate(RecurringCost $schedule, int $limit = 100): int
    {
        $created = 0;
        for ($i = 0; $i < $limit; $i++) {
            try {
                $didCreate = DB::transaction(function () use ($schedule): bool {
                    $record = RecurringCost::query()->whereKey($schedule->id)->lockForUpdate()->firstOrFail();
                    $date = $record->next_run_at;
                    if ($record->status !== 'ACTIVE' || ! $date || $date > CarbonImmutable::now($record->timezone)->toDateString()) {
                        return false;
                    }
                    if ($this->finished($record, $date)) {
                        $record->update(['status' => 'COMPLETED']);

                        return false;
                    }
                    $occurrence = RecurringCostOccurrence::query()->where('recurring_cost_id', $record->id)->where('scheduled_for', $date)->first();
                    if (! $occurrence) {
                        $template = $this->validateTemplate($record->company_id, $record->toArray(), [], true);
                        if ($record->mode === 'BILL') {
                            $generated = $this->documents->saveBill(null, $record->company_id, $record->creator_id, [...$template, 'supplier_id' => $record->supplier_id, 'document_date' => $date, 'due_date' => CarbonImmutable::parse($date)->addDays($record->due_days)->toDateString(), 'status' => 'OPEN']);
                        } else {
                            PurchaseInputs::ensure($record->auto_record_paid, 'auto_record_paid', 'Automatic paid-expense recording is disabled.');
                            $supplier = PurchaseInputs::lockSupplier($record->company_id, $record->supplier_id);
                            PurchaseInputs::ensure($supplier->enabled, 'supplier_id', 'This supplier is inactive.');
                            $money = PurchaseInputs::money($record->company_id, $template);
                            $attributes = [...Arr::except($template, ['taxes']), ...$money, 'company_id' => $record->company_id, 'creator_id' => $record->creator_id, 'supplier_id' => $record->supplier_id, 'expense_date' => $date, 'expense_number' => 'RC-'.$record->id.'-'.$date, 'base_amount' => PurchaseInputs::base($template['amount'], $money['exchange_rate'])];
                            $generated = $this->expenses->create($attributes, $template['taxes'] ?? []);
                        }
                        $record->occurrences()->create(['company_id' => $record->company_id, 'scheduled_for' => $date, 'record_type' => $generated->getMorphClass(), 'record_id' => $generated->id]);
                        $record->occurrence_count++;
                        PurchaseAudit::record($record, 'generated', $record->creator_id, ['scheduled_for' => $date, 'record_type' => $generated->getMorphClass(), 'record_id' => $generated->id]);
                    }
                    $record->next_run_at = $this->nextDate($record, $date);
                    $record->last_error = null;
                    if ($this->finished($record, $record->next_run_at)) {
                        $record->status = 'COMPLETED';
                    }
                    $record->save();

                    return ! $occurrence;
                });
                if (! $didCreate) {
                    break;
                }
                $created++;
            } catch (Throwable $error) {
                $message = $error instanceof ValidationException ? implode(' ', Arr::flatten($error->errors())) : 'Generation failed. Check the application log and retry.';
                RecurringCost::query()->whereKey($schedule->id)->update(['last_error' => $message]);
                report($error);
                break;
            }
        }

        return $created;
    }

    public function nextDate(RecurringCost $record, string $date): string
    {
        $from = CarbonImmutable::parse($date, $record->timezone);
        $anchor = CarbonImmutable::parse($record->starts_at, $record->timezone);

        return match ($record->frequency) {
            'DAY' => $from->addDays($record->interval)->toDateString(),
            'WEEK' => $from->addWeeks($record->interval)->toDateString(),
            'MONTH' => $this->anchored($from->startOfMonth()->addMonths($record->interval), $anchor->day)->toDateString(),
            'YEAR' => $this->anchored($from->startOfYear()->addYears($record->interval)->month($anchor->month), $anchor->day)->toDateString(),
        };
    }

    private function anchored(CarbonImmutable $date, int $day): CarbonImmutable
    {
        return $date->day(min($day, $date->daysInMonth));
    }

    private function finished(RecurringCost $record, string $next): bool
    {
        return ($record->ends_at && $next > $record->ends_at) || ($record->max_occurrences && $record->occurrence_count >= $record->max_occurrences);
    }

    private function validateTemplate(int $companyId, array $data, array $existing = [], bool $generating = false): array
    {
        $input = $data['template'];
        if ($data['mode'] === 'BILL') {
            if ($generating) {
                $input['customFields'] = $this->customFields->retained($companyId, 'Bill', $input['customFields'] ?? []);
            }
            $input['customFields'] = $this->customFields->resolve($companyId, 'Bill', $input['customFields'] ?? [], $this->customFields->retained($companyId, 'Bill', $existing), 'template.customFields');
            $validated = Validator::make([...$input, 'supplier_id' => $data['supplier_id'], 'document_date' => $data['starts_at'], 'due_date' => $data['starts_at'], 'status' => 'OPEN'], BillRequest::rulesFor($companyId))->validate();

            return Arr::except($validated, ['document_date', 'due_date', 'status']);
        }
        $rules = [
            'customFields' => ['prohibited'],
            'amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')],
            'exchange_rate' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'expense_category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')->where('company_id', $companyId)],
            'payment_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'id')->where('company_id', $companyId)],
            'notes' => ['nullable', 'string', 'max:10000'],
            'taxes' => ['sometimes', 'array'],
            'taxes.*.tax_type_id' => ['required', 'integer', 'distinct', Rule::exists('tax_types', 'id')->where('company_id', $companyId)->where('type', TaxType::TYPE_GENERAL)->where('transaction_type', TaxType::TRANSACTION_TYPE_PURCHASES)],
            'taxes.*.amount' => ['required', 'integer', 'min:0'],
        ];
        $validated = Validator::make($input, $rules)->validate();
        PurchaseInputs::ensure(array_sum(array_column($validated['taxes'] ?? [], 'amount')) <= $validated['amount'], 'template.taxes', 'Purchase tax cannot exceed the expense amount.');

        return $validated;
    }
}
