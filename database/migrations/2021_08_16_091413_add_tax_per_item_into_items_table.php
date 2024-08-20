<?php

use App\Models\Item;
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
        Schema::table('items', function (Blueprint $table) {
            $table->boolean('tax_per_item')->default(false);
        });

        $items = Item::with('taxes')->get();

        if ($items) {
            foreach ($items as $item) {
                if (! $item->taxes()->get()->isEmpty()) {
                    $item->tax_per_item = true;
                    $item->save();
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('tax_per_item');
        });
    }
};
