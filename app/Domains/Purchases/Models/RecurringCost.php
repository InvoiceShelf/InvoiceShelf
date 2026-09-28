<?php

namespace App\Domains\Purchases\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class RecurringCost extends Model
{
    protected $table = 'recurring_costs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'template' => 'array',
            'auto_record_paid' => 'boolean',
            'occurrence_count' => 'integer',
            'interval' => 'integer',
            'max_occurrences' => 'integer',
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

    public function activities(): MorphMany
    {
        return $this->morphMany(PurchaseActivity::class, 'subject')->orderByDesc('id');
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query, $value, $field)->where($this->qualifyColumn('company_id'), (int) request()->header('company'));
    }
}
