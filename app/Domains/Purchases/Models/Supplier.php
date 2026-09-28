<?php

namespace App\Domains\Purchases\Models;

use App\Domains\Metadata\Concerns\HasCustomFields;
use App\Domains\Money\Models\Currency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasCustomFields;

    protected $table = 'suppliers';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'addresses' => 'array',
            'enabled' => 'boolean',
            'payment_terms' => 'integer',
        ];
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where($this->qualifyColumn('company_id'), $companyId);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class, 'supplier_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class, 'supplier_id');
    }

    public function credits(): HasMany
    {
        return $this->hasMany(SupplierCredit::class, 'supplier_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(SupplierRefund::class, 'supplier_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(PurchaseActivity::class, 'supplier_id')->orderByDesc('id');
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query, $value, $field)->where($this->qualifyColumn('company_id'), (int) request()->header('company'));
    }
}
