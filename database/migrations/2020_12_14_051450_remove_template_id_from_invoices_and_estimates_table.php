<?php

use App\Models\Estimate;
use App\Models\Invoice;
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
        if (Schema::hasColumn('invoices', 'invoice_template_id')) {
            $invoices = Invoice::all();

            $invoices->map(function ($invoice) {
                $invoice->template_name = 'invoice'.$invoice->invoice_template_id;
                $invoice->save();
            });

            Schema::table('invoices', function (Blueprint $table) {
                if (config('database.default') !== 'sqlite') {
                    $table->dropForeign(['invoice_template_id']);
                }
                $table->dropColumn('invoice_template_id');
            });
        }

        if (Schema::hasColumn('estimates', 'estimate_template_id')) {
            $estimates = Estimate::all();

            $estimates->map(function ($estimate) {
                $estimate->template_name = 'estimate'.$estimate->estimate_template_id;
                $estimate->save();
            });

            Schema::table('estimates', function (Blueprint $table) {
                if (config('database.default') !== 'sqlite') {
                    $table->dropForeign(['estimate_template_id']);
                }
                $table->dropColumn('estimate_template_id');
            });
        }

        Schema::dropIfExists('invoice_templates');
        Schema::dropIfExists('estimate_templates');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
