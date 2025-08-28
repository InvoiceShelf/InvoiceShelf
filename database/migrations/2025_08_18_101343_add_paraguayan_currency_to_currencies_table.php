<?php

use App\Models\Currency;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Currency::firstOrCreate(
            ['code' => 'PYG'],
            [
                'name' => 'Paraguayan Guaraní',
                'symbol' => '₲',
                'precision' => '0',
                'thousand_separator' => '.',
                'decimal_separator' => ',',
            ]
        );
    }
};
