<?php

namespace App\Domains\Purchases\Models;

use App\Domains\Accounts\Models\CompanySetting;
use App\Support\Recurrence\RecurringSchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A recurring bill (an unpaid bill with a due date, in payables) or a
 * recurring paid expense, generated from a stored template on a cron
 * schedule by App\Support\Recurrence.
 */
class RecurringCost extends Model implements RecurringSchedule
{
    /** Generates an unpaid bill for each run. */
    public const MODE_BILL = 'BILL';

    /** Generates an expense already paid for each run. */
    public const MODE_EXPENSE = 'EXPENSE';

    protected $table = 'recurring_costs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'template' => 'array',
            'create_as_draft' => 'boolean',
            'notify_creator' => 'boolean',
            'limit_count' => 'integer',
            'due_days' => 'integer',
        ];
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where($this->qualifyColumn('company_id'), $companyId);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(RecurringCostOccurrence::class, 'recurring_cost_id');
    }

    public function nextRunColumn(): string
    {
        return 'next_run_at';
    }

    public function generatedCount(): int
    {
        return $this->occurrences()->count();
    }

    /**
     * A bill or expense that was owed while the scheduler was down is still
     * owed, so every missed run is generated.
     */
    public function catchesUp(): bool
    {
        return true;
    }

    public function scheduleTimeZone(): string
    {
        return CompanySetting::timeZone($this->company_id);
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query, $value, $field)
            ->where($this->qualifyColumn('company_id'), (int) request()->header('company'));
    }
}
