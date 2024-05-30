<?php

use App\Models\CompanySetting;
use App\Models\Expense;
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
            $companyId = $user->companies()->first()->id;

            $currency_id = CompanySetting::getSetting('currency', $companyId);

            $expenses = Expense::where('company_id', $companyId)->where('currency_id', null)->get();
            if ($expenses) {
                $expenses->map(function ($expense) use ($currency_id) {
                    $expense->update([
                        'currency_id' => $currency_id,
                        'exchange_rate' => 1,
                        'base_amount' => $expense->amount,
                    ]);
                });
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
