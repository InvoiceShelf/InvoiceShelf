<?php

namespace App\Platform\Recurrence\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One run of a recurring schedule: the moment it was due, the day it was
 * dated where the company is, and the record it generated. Kept when that
 * record is deleted, so a schedule's count of runs stays true.
 */
class RecurrenceOccurrence extends Model
{
    protected $table = 'recurrence_occurrences';

    protected $guarded = ['id'];

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where($this->qualifyColumn('company_id'), $companyId);
    }

    public function schedule(): MorphTo
    {
        return $this->morphTo();
    }

    public function record(): MorphTo
    {
        return $this->morphTo();
    }
}
