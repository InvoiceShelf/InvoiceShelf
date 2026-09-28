<?php

namespace App\Domains\Purchases\Models;

use App\Domains\Metadata\Concerns\HasCustomFields;
use App\Domains\Money\Models\Currency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Bill extends Model implements HasMedia
{
    use HasCustomFields;
    use InteractsWithMedia;

    protected $table = 'bills';

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
        return $this->hasMany(BillItem::class, 'bill_id');
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class, 'bill_id');
    }

    public function creditAllocations(): HasMany
    {
        return $this->hasMany(SupplierCreditAllocation::class, 'bill_id');
    }

    public function credits(): HasMany
    {
        return $this->hasMany(SupplierCredit::class, 'source_bill_id');
    }

    public function getSettlementStatusAttribute(): string
    {
        return $this->due_amount === 0 ? 'SETTLED' : ($this->due_amount < $this->total ? 'PARTIAL' : 'UNPAID');
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query, $value, $field)->where($this->qualifyColumn('company_id'), (int) request()->header('company'));
    }
}
