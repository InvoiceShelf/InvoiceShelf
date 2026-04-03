<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRateProvider extends Model
{
    use HasFactory;

    protected $guarded = [
        'id',
    ];

    protected function casts(): array
    {
        return [
            'currencies' => 'array',
            'driver_config' => 'array',
            'active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function setCurrenciesAttribute($value)
    {
        $this->attributes['currencies'] = json_encode($value);
    }

    public function setDriverConfigAttribute($value)
    {
        $this->attributes['driver_config'] = json_encode($value);
    }

    public function scopeWhereCompany($query)
    {
        $query->where('exchange_rate_providers.company_id', request()->header('company'));
    }
}
