<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory;

    public const COMMON_CURRENCY_CODES = [
        'USD', 'EUR', 'GBP', 'JPY', 'CAD',
        'AUD', 'CHF', 'CNY', 'INR', 'BRL',
    ];

    protected $guarded = [
        'id',
    ];
}
