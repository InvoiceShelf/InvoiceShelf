<?php

namespace App\Domains\Purchases\Models;

use App\Domains\Money\Models\Currency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class SupplierCredit extends Model implements HasMedia
{
    use InteractsWithMedia;

    /** The company setting that holds this document's number format. */
    public const NUMBER_FORMAT_SETTING = 'supplier_credit_number_format';

    /** The number format used while the company has not set one. */
    public const DEFAULT_NUMBER_FORMAT = '{{SERIES:SC}}{{DELIMITER:-}}{{SEQUENCE:6}}';

    protected $table = 'supplier_credits';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tax_included' => 'boolean',
            'sub_total' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
            'base_total' => 'integer',
            'due_amount' => 'integer',
            'base_due_amount' => 'integer',
            'exchange_rate' => 'float',
            'supplier_snapshot' => 'array',
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

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierCreditItem::class, 'supplier_credit_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(SupplierCreditAllocation::class, 'supplier_credit_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(SupplierRefund::class, 'supplier_credit_id');
    }

    public function sourceBill(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'source_bill_id');
    }

    public function sourceExpense(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'source_expense_id');
    }

    public function getAvailableAmountAttribute(): int
    {
        return $this->status === 'OPEN' ? (int) $this->total - (int) $this->allocations->sum('amount') - (int) $this->refunds->where('status', 'OPEN')->sum('amount') : 0;
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query, $value, $field)->where($this->qualifyColumn('company_id'), (int) request()->header('company'));
    }
}
