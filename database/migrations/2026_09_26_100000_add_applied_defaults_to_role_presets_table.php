<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Records which catalogue defaults each role preset has already been offered,
 * so a new ability reaches the shipped presets through its `presets` entry in
 * config/abilities.php instead of a migration of its own.
 *
 * Manager and Read only are marked with exactly the lists the create migration
 * seeded them with. That is a snapshot, not a read of the configuration: an
 * install that reaches a later release in one step must still be offered every
 * ability tagged since, and one the super administrator removed in the
 * meantime is already on the list and stays removed.
 *
 * Owner is left unmarked. It is offered the whole catalogue, so the first
 * `migrate` after this one gives every company's owner role anything it lacks,
 * once; after that only abilities new to the catalogue are handed out.
 */
return new class extends Migration
{
    private const MANAGER = [
        'view-customer', 'create-customer', 'edit-customer', 'delete-customer',
        'view-item', 'create-item', 'edit-item', 'delete-item',
        'view-tax-type', 'create-tax-type', 'edit-tax-type', 'delete-tax-type',
        'view-estimate', 'create-estimate', 'edit-estimate', 'delete-estimate', 'send-estimate',
        'view-invoice', 'create-invoice', 'edit-invoice', 'delete-invoice', 'send-invoice',
        'view-recurring-invoice', 'create-recurring-invoice', 'edit-recurring-invoice', 'delete-recurring-invoice',
        'view-payment', 'create-payment', 'edit-payment', 'delete-payment', 'send-payment',
        'view-expense', 'create-expense', 'edit-expense', 'delete-expense',
        'view-custom-field', 'view-financial-reports', 'view-exchange-rate-provider',
        'dashboard', 'view-all-notes', 'manage-all-notes',
    ];

    private const READ_ONLY = [
        'view-customer', 'view-item', 'view-tax-type', 'view-estimate', 'view-invoice',
        'view-recurring-invoice', 'view-payment', 'view-expense', 'view-custom-field',
        'view-financial-reports', 'view-exchange-rate-provider', 'dashboard', 'view-all-notes',
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('role_presets', 'applied_defaults')) {
            Schema::table('role_presets', function (Blueprint $table) {
                $table->json('applied_defaults')->nullable()->after('abilities');
            });
        }

        foreach (['manager' => self::MANAGER, 'read-only' => self::READ_ONLY] as $key => $seeded) {
            DB::table('role_presets')
                ->where('key', $key)
                ->whereNull('applied_defaults')
                ->update(['applied_defaults' => json_encode($seeded)]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('role_presets', 'applied_defaults')) {
            Schema::table('role_presets', function (Blueprint $table) {
                $table->dropColumn('applied_defaults');
            });
        }
    }
};
