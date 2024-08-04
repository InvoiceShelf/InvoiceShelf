<?php

use App\Models\TaxType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tax_types', function (Blueprint $table) {
            $table->enum('type', ['GENERAL', 'MODULE'])->default(TaxType::TYPE_GENERAL);
        });

        $taxTypes = TaxType::all();

        if ($taxTypes) {
            foreach ($taxTypes as $taxType) {
                $taxType->type = TaxType::TYPE_GENERAL;
                $taxType->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tax_types', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
