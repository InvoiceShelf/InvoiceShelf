<?php

namespace App\Domains\Purchases\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PurchaseActivity extends Model
{
    protected $table = 'purchase_activities';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'details' => 'array',
        ];
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where($this->qualifyColumn('company_id'), $companyId);
    }
}
