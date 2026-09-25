<?php

use App\Domains\Accounts\Application\RolePresetService;
use App\Domains\Accounts\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Silber\Bouncer\BouncerFacade;
use Silber\Bouncer\Database\Role;

/**
 * Role presets: roles the super administrator defines for every company.
 *
 * Ships three: Owner (the `owner` role every company already has), Manager and
 * Read only, then gives every existing company its copies. The lists are a
 * snapshot of the catalogue as it stands today, so a later change to
 * config/abilities.php does not rewrite what this migration seeded.
 *
 * No DDL is wrapped in try/catch: PostgreSQL aborts the whole transaction on a
 * failed statement. The guards make a retry after a partial MySQL run safe.
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
        if (! Schema::hasTable('role_presets')) {
            Schema::create('role_presets', function (Blueprint $table) {
                $table->increments('id');
                $table->string('key', 64)->unique();
                $table->string('title', 100);
                $table->json('abilities')->nullable();
                $table->timestamps();
            });
        }

        $now = now();

        DB::table('role_presets')->insertOrIgnore([
            ['key' => 'owner', 'title' => 'Owner', 'abilities' => null, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'manager', 'title' => 'Manager', 'abilities' => json_encode(self::MANAGER), 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'read-only', 'title' => 'Read only', 'abilities' => json_encode(self::READ_ONLY), 'created_at' => $now, 'updated_at' => $now],
        ]);

        app(RolePresetService::class)->syncAll();
    }

    public function down(): void
    {
        if (Schema::hasTable('companies')) {
            foreach (Company::query()->pluck('id') as $companyId) {
                BouncerFacade::scope()->onceTo((int) $companyId, function () use ($companyId): void {
                    Role::query()->withoutGlobalScopes()
                        ->where('scope', $companyId)
                        ->where('name', 'like', 'preset:%')
                        ->get()
                        ->each(fn (Role $role) => $role->delete());
                });
            }
        }

        Schema::dropIfExists('role_presets');
    }
};
