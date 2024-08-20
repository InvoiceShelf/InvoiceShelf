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
        $this->replaceModelTypes('InvoiceShelf', 'App');
        $this->replaceModelTypes('Crater', 'App');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->replaceModelTypes('App', 'InvoiceShelf');
    }

    /**
     * Replace model types in the specified tables.
     */
    private function replaceModelTypes(string $from, string $to): void
    {
        $mappings = [
            'media' => 'model_type',
            'email_logs' => 'mailable_type',
            'notifications' => 'notifiable_type',
            'personal_access_tokens' => 'tokenable_type',
            'custom_fields' => 'model_type',
            'custom_field_values' => 'custom_field_valuable_type',
            'abilities' => 'entity_type',
            'assigned_roles' => 'entity_type',
        ];

        foreach ($mappings as $table => $column) {
            DB::table($table)->update([$column => DB::raw("REPLACE($column, '$from', '$to')")]);
        }
    }
};
