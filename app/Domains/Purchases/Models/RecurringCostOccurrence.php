<?php

namespace App\Domains\Purchases\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * The bill or expense one run of a recurring cost generated, and the day it
 * was scheduled for.
 */
class RecurringCostOccurrence extends Model
{
    protected $table = 'recurring_cost_occurrences';

    protected $guarded = ['id'];

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where($this->qualifyColumn('company_id'), $companyId);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(RecurringCost::class, 'recurring_cost_id');
    }

    public function record(): MorphTo
    {
        return $this->morphTo();
    }
}
