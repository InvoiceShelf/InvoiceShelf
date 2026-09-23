<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Collapse duplicate currency rows and make a repeat impossible.
 *
 * Every 3.x installation built from scratch carries `DZD`, `PYG` and `QAR`
 * twice: the base-schema consolidation inserted those three, and
 * `CurrenciesTableSeeder` then inserted the whole list over the top. The
 * consolidation no longer seeds them and the seeder no longer blind-inserts,
 * so this repairs what already shipped.
 *
 * The unique index is the point. Currency rows now arrive through
 * `CurrencyService::sync()`, including from a button in the admin area, and
 * the index is what makes "run it as often as you like" a property of the
 * schema rather than a promise in a service.
 */
return new class extends Migration
{
    /**
     * Every column that points at `currencies.id`.
     *
     * @var array<string, list<string>>
     */
    private const REFERENCES = [
        'users' => ['currency_id'],
        'items' => ['currency_id'],
        'customers' => ['currency_id'],
        'expenses' => ['currency_id'],
        'invoices' => ['currency_id'],
        'estimates' => ['currency_id'],
        'payments' => ['currency_id'],
        'taxes' => ['currency_id'],
        'recurring_invoices' => ['currency_id'],
        'exchange_rate_logs' => ['currency_id', 'base_currency_id'],
    ];

    public function up(): void
    {
        $this->collapseDuplicates();

        Schema::table('currencies', function (Blueprint $table): void {
            $table->unique('code');
        });
    }

    /**
     * Only the index comes back off. The collapsed rows were duplicates of
     * rows that are still here, so there is nothing to restore and no way to
     * tell which reference pointed at which copy.
     */
    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table): void {
            $table->dropUnique(['code']);
        });
    }

    /**
     * Keep the lowest id of each code and move every reference onto it.
     *
     * Repointing before deleting is not tidiness: `users.currency_id` is
     * declared `onDelete('cascade')`, so deleting a duplicate row out from
     * under a user would delete the user.
     */
    private function collapseDuplicates(): void
    {
        $duplicated = DB::table('currencies')
            ->select('code')
            ->groupBy('code')
            ->havingRaw('count(*) > 1')
            ->pluck('code');

        foreach ($duplicated as $code) {
            $ids = DB::table('currencies')
                ->where('code', $code)
                ->orderBy('id')
                ->pluck('id');

            $keep = $ids->shift();
            $drop = $ids->all();

            foreach (self::REFERENCES as $table => $columns) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                foreach ($columns as $column) {
                    if (! Schema::hasColumn($table, $column)) {
                        continue;
                    }

                    DB::table($table)
                        ->whereIn($column, $drop)
                        ->update([$column => $keep]);
                }
            }

            // A company's own currency is not a foreign key: it is a settings
            // row holding the id as text.
            DB::table('company_settings')
                ->where('option', 'currency')
                ->whereIn('value', array_map(strval(...), $drop))
                ->update(['value' => (string) $keep]);

            DB::table('currencies')->whereIn('id', $drop)->delete();
        }
    }
};
