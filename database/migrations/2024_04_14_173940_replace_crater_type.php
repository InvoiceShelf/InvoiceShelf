<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            DB::update("UPDATE media SET model_type = REPLACE(model_type, 'Crater', 'InvoiceShelf')");
            DB::update("UPDATE email_logs SET mailable_type = REPLACE(model_type, 'Crater', 'InvoiceShelf')");
            DB::update("UPDATE notifications SET notifiable_type = REPLACE(notifiable_type, 'Crater', 'InvoiceShelf')");
            DB::update("UPDATE personal_access_tokens SET tokenable_type = REPLACE(tokenable_type, 'Crater', 'InvoiceShelf')");
            DB::update("UPDATE custom_field_values SET custom_field_valuable_type = REPLACE(custom_field_valuable_type, 'Crater', 'InvoiceShelf')");
        } catch (\Exception $e) {
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::update("UPDATE media SET model_type = REPLACE(model_type, 'InvoiceShelf', 'Crater')");
            DB::update("UPDATE email_logs SET mailable_type = REPLACE(model_type, 'InvoiceShelf', 'Crater')");
            DB::update("UPDATE notifications SET notifiable_type = REPLACE(notifiable_type, 'InvoiceShelf', 'Crater')");
            DB::update("UPDATE personal_access_tokens SET tokenable_type = REPLACE(tokenable_type, 'InvoiceShelf', 'Crater')");
            DB::update("UPDATE custom_field_values SET custom_field_valuable_type = REPLACE(custom_field_valuable_type, 'InvoiceShelf', 'Crater')");
        } catch (\Exception $e) {
        }
    }
};
