<?php

namespace App\Domains\Purchases\Models;

use App\Domains\Money\Models\Currency;
use App\Domains\Receivables\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierPayment extends Model
{
    /** The company setting that holds this document's number format. */
    public const NUMBER_FORMAT_SETTING = 'supplier_payment_number_format';

    /** The number format used while the company has not set one. */
    public const DEFAULT_NUMBER_FORMAT = '{{SERIES:SP}}{{DELIMITER:-}}{{SEQUENCE:6}}';

    protected $table = 'supplier_payments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'base_amount' => 'integer',
            'exchange_rate' => 'float',
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

    public function allocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class, 'supplier_payment_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(SupplierRefund::class, 'supplier_payment_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function getAvailableAmountAttribute(): int
    {
        return $this->status === 'OPEN' ? (int) $this->amount - (int) $this->allocations->sum('amount') - (int) $this->refunds->where('status', 'OPEN')->sum('amount') : 0;
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query, $value, $field)->where($this->qualifyColumn('company_id'), (int) request()->header('company'));
    }
}
