<?php

use App\Models\Company;
use App\Models\PaymentMethod;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Company::chunkById(100, function ($companies) {
            foreach ($companies as $company) {
                $exists = PaymentMethod::where('company_id', $company->id)
                    ->whereRaw('LOWER(name) = ?', ['interac e-transfer'])
                    ->exists();

                if (! $exists) {
                    PaymentMethod::create([
                        'name' => 'Interac e-Transfer',
                        'company_id' => $company->id,
                    ]);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        PaymentMethod::whereRaw('LOWER(name) = ?', ['interac e-transfer'])->delete();
    }
};
