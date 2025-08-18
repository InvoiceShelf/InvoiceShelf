<?php

use App\Models\Currency;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Currency::create([
            'name' => 'Paraguayan Guaraní',
            'code' => 'PYG',
            'symbol' => '₲',
            'precision' => '0',
            'thousand_separator' => '.',
            'decimal_separator' => ',',
        ]);
    }

    public function down(): void
    {
        Currency::where('code', 'PYG')->delete();
    }
};
