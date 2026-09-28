<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['payments' => 'customer_payments', 'payment_allocations' => 'customer_payment_allocations'];

    private const TYPES = ['payment' => 'customer_payment', 'payment_allocation' => 'customer_payment_allocation'];

    private const COLUMNS = [
        'media' => ['model_type'],
        'oauth_clients' => ['owner_type'],
        'email_logs' => ['mailable_type'],
        'notifications' => ['notifiable_type'],
        'personal_access_tokens' => ['tokenable_type'],
        'custom_field_values' => ['custom_field_valuable_type'],
        'abilities' => ['entity_type'],
        'assigned_roles' => ['entity_type', 'restricted_to_type'],
        'permissions' => ['entity_type'],
    ];

    public function up(): void
    {
        $this->rename(self::TABLES, self::TYPES);
    }

    public function down(): void
    {
        $this->rename(array_flip(self::TABLES), array_flip(self::TYPES));
    }

    private function rename(array $tables, array $types): void
    {
        foreach ($tables as $old => $new) {
            if (Schema::hasTable($old)) {
                if (Schema::hasTable($new)) {
                    throw new RuntimeException("Cannot rename {$old}: {$new} already exists.");
                }
                Schema::rename($old, $new);
            }
        }
        DB::transaction(function () use ($types): void {
            foreach (self::COLUMNS as $table => $columns) {
                foreach ($columns as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        foreach ($types as $old => $new) {
                            DB::table($table)->where($column, $old)->update([$column => $new]);
                        }
                    }
                }
            }
        });
    }
};
