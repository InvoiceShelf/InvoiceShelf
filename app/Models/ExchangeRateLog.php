<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRateLog extends Model
{
    use HasFactory;

    protected $guarded = [
        'id',
    ];

    protected function casts(): array
    {
        return [
            'exchange_rate' => 'float',
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function addExchangeRateLog($model)
    {
        $data = [
            'exchange_rate' => $model->exchange_rate,
            'company_id' => $model->company_id,
            'base_currency_id' => $model->currency_id,
            'currency_id' => CompanySetting::getSetting('currency', $model->company_id),
        ];

        return self::create($data);
    }
}
