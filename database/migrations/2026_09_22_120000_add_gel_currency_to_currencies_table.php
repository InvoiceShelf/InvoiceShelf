<?php

use App\Models\Currency;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Currency::firstOrCreate(
            ['code' => 'GEL'],
            [
                'name' => 'Georgian Lari',
                'symbol' => '₾',
                'precision' => '2',
                'thousand_separator' => ',',
                'decimal_separator' => '.',
                'swap_currency_symbol' => true,
            ]);
    }
};
