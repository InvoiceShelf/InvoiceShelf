<?php

use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $user = User::where('role', 'super admin')->first();

        if ($user) {
            $invoice_number_length = CompanySetting::getSetting('invoice_number_length', $user->company_id);
            if (empty($invoice_number_length)) {
                CompanySetting::setSettings(['invoice_number_length' => '6'], $user->company_id);
            }

            $estimate_number_length = CompanySetting::getSetting('estimate_number_length', $user->company_id);
            if (empty($estimate_number_length)) {
                CompanySetting::setSettings(['estimate_number_length' => '6'], $user->company_id);
            }

            $payment_number_length = CompanySetting::getSetting('payment_number_length', $user->company_id);
            if (empty($payment_number_length)) {
                CompanySetting::setSettings(['payment_number_length' => '6'], $user->company_id);
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
