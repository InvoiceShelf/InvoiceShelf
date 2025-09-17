<?php

use App\Models\Currency;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {

        Currency::firstOrCreate(
            ['code' => 'QAR'],
            [
                'name' => 'Qatari Riyal',
                'symbol' => 'QR',
                'precision' => '2',
                'thousand_separator' => ',',
                'decimal_separator' => '.',
            ]);
    }
};
