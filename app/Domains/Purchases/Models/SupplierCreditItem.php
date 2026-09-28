<?php

namespace App\Domains\Purchases\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierCreditItem extends Model
{
    protected $table = 'supplier_credit_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'price' => 'integer',
            'sub_total' => 'integer',
            'discount_val' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
            'base_total' => 'integer',
            'taxes' => 'array',
        ];
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where($this->qualifyColumn('company_id'), $companyId);
    }

    public function credit(): BelongsTo
    {
        return $this->belongsTo(SupplierCredit::class, 'supplier_credit_id');
    }

    public function sourceItem(): BelongsTo
    {
        return $this->belongsTo(BillItem::class, 'source_bill_item_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }
}
