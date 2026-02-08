<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use App\Models\User;
use App\Models\CompanySetting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedMediumInteger('sequence_number')->nullable();
            $table->unsignedMediumInteger('customer_sequence_number')->nullable();
            $table->string('credit_note_number');
            $table->date('credit_note_date');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('amount');
            $table->string('unique_hash')->nullable();
            $table->unsignedInteger('invoice_id')->nullable();
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->unsignedInteger('company_id')->nullable();
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->decimal('exchange_rate', 19, 6)->nullable();
            $table->unsignedBigInteger('base_amount')->nullable();
            $table->unsignedInteger('currency_id')->nullable();
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->unsignedInteger('creator_id')->nullable();
            $table->foreign('creator_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->foreign('transaction_id')->references('id')->on('transactions');
            $table->timestamps();
        });


        $user = User::where('role', 'super admin')->first();

        $updatedSettings = [
            'credit_note_auto_generate' => 'YES',
            'credit_note_mail_body' => 'We have issued a credit note for your invoice.</b></br> Please download your credit note using the button below:',
            'credit_note_company_address_format' => '<h3><strong>{COMPANY_NAME}</strong></h3><p>{COMPANY_ADDRESS_STREET_1}</p><p>{COMPANY_ADDRESS_STREET_2}</p><p>{COMPANY_CITY} {COMPANY_STATE}</p><p>{COMPANY_COUNTRY}  {COMPANY_ZIP_CODE}</p><p>{COMPANY_PHONE}</p>',
            'credit_note_email_attachment' => 'NO',
            'creditnote_number_format' => '{{SERIES:CN}}{{DELIMITER:-}}{{SEQUENCE:6}}',
        ];

        if ($user) {
            foreach ($updatedSettings as $setting => $value) {
                if (empty(CompanySetting::getSetting($setting, $user->company_id))) {
                   CompanySetting::setSettings([$setting => $value], $user->company_id);
            }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};
