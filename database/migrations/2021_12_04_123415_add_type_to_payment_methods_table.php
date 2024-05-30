<?php

use App\Models\PaymentMethod;
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
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->string('driver')->nullable();
            $table->enum('type', ['GENERAL', 'MODULE'])->default(PaymentMethod::TYPE_GENERAL);
            $table->json('settings')->nullable();
            $table->boolean('active')->default(false);
            $table->boolean('use_test_env')->default(false);
        });

        $paymentMethods = PaymentMethod::all();

        if ($paymentMethods) {
            foreach ($paymentMethods as $paymentMethod) {
                $paymentMethod->type = PaymentMethod::TYPE_GENERAL;
                $paymentMethod->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn([
                'driver',
                'type',
                'settings',
                'active',
            ]);
        });
    }
};
