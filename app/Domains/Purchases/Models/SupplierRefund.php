<?php

namespace App\Domains\Purchases\Models;

use App\Domains\Money\Models\Currency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierRefund extends Model
{
    /** The company setting that holds this document's number format. */
    public const NUMBER_FORMAT_SETTING = 'supplier_refund_number_format';

    /** The number format used while the company has not set one. */
    public const DEFAULT_NUMBER_FORMAT = '{{SERIES:SR}}{{DELIMITER:-}}{{SEQUENCE:6}}';

    protected $table = 'supplier_refunds';

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

    public function payment(): BelongsTo
    {
        return $this->belongsTo(SupplierPayment::class, 'supplier_payment_id');
    }

    public function credit(): BelongsTo
    {
        return $this->belongsTo(SupplierCredit::class, 'supplier_credit_id');
    }

    /**
     * Bind route parameters only to the current company's records.
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query, $value, $field)
            ->where($this->qualifyColumn('company_id'), (int) request()->header('company'));
    }
}
