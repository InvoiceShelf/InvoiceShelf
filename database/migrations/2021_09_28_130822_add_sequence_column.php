<?php

use App\Models\Customer;
use App\Models\User;
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
        Schema::table('customers', function (Blueprint $table) {
            $table->string('prefix')->nullable()->after('id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->mediumInteger('sequence_number')->unsigned()->nullable()->after('id');
            $table->mediumInteger('customer_sequence_number')->unsigned()->nullable()->after('sequence_number');
        });

        Schema::table('estimates', function (Blueprint $table) {
            $table->mediumInteger('sequence_number')->unsigned()->nullable()->after('id');
            $table->mediumInteger('customer_sequence_number')->unsigned()->nullable()->after('sequence_number');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->mediumInteger('sequence_number')->unsigned()->nullable()->after('id');
            $table->mediumInteger('customer_sequence_number')->unsigned()->nullable()->after('sequence_number');
        });

        $user = User::where('role', 'super admin')->first();

        if ($user && $user->role == 'super admin') {
            $customers = Customer::all();
            foreach ($customers as $customer) {
                $invoices = $customer->invoices;
                if ($invoices) {
                    $customerSequence = 1;
                    $invoices->map(function ($invoice) use ($customerSequence) {
                        $invoiceNumber = explode('-', $invoice->invoice_number);
                        $invoice->sequence_number = intval(end($invoiceNumber));
                        $invoice->customer_sequence_number = $customerSequence;
                        $invoice->save();
                        $customerSequence += 1;
                    });
                }

                $estimates = $customer->estimates;
                if ($estimates) {
                    $customerSequence = 1;
                    $estimates->map(function ($estimate) use ($customerSequence) {
                        $estimateNumber = explode('-', $estimate->estimate_number);
                        $estimate->sequence_number = intval(end($estimateNumber));
                        $estimate->customer_sequence_number = $customerSequence;
                        $estimate->save();
                        $customerSequence += 1;
                    });
                }

                $payments = $customer->payments;
                if ($estimates) {
                    $customerSequence = 1;
                    $payments->map(function ($payment) use ($customerSequence) {
                        $paymentNumber = explode('-', $payment->payment_number);
                        $payment->sequence_number = intval(end($paymentNumber));
                        $payment->customer_sequence_number = $customerSequence;
                        $payment->save();
                        $customerSequence += 1;
                    });
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('sequence_number');
            $table->dropColumn('customer_sequence_number');
        });
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropColumn('sequence_number');
            $table->dropColumn('customer_sequence_number');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('sequence_number');
            $table->dropColumn('customer_sequence_number');
        });
    }
};
