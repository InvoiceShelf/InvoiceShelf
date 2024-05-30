<?php

use App\Models\Payment;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $payments = Payment::where('exchange_rate', '<>', null)->get();

        if ($payments) {
            foreach ($payments as $payment) {
                $payment->base_amount = $payment->exchange_rate * $payment->amount;
                $payment->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
