<?php

use App\Support\PublicToken;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Give every document and every sent email a new, random link token.
 *
 * The old ones were Hashids of the row id, salted with a class name followed
 * by APP_KEY. Hashids reads only the start of a salt, so the key never
 * counted: every installation gave the same id the same token, and anyone
 * could walk the ids and open the documents behind the public links. There
 * is no telling which old tokens were legitimately handed out, so they all
 * go. Links in emails already sent stop working; the documents are still in
 * the customer portal, and can be sent again.
 *
 * The query builder rather than the models: this must not touch
 * `updated_at` or fire model events on every document an install has.
 */
return new class extends Migration
{
    /** @var array<string, string> table => column */
    private const TOKENS = [
        'invoices' => 'unique_hash',
        'estimates' => 'unique_hash',
        'payments' => 'unique_hash',
        'transactions' => 'unique_hash',
        'email_logs' => 'token',
    ];

    public function up(): void
    {
        foreach (self::TOKENS as $table => $column) {
            if (! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::table($table)
                ->select('id')
                ->whereNotNull($column)
                ->chunkById(500, function ($rows) use ($table, $column): void {
                    DB::transaction(function () use ($rows, $table, $column): void {
                        foreach ($rows as $row) {
                            DB::table($table)->where('id', $row->id)->update([$column => PublicToken::make()]);
                        }
                    });
                });
        }
    }

    /**
     * Irreversible by design: the old tokens are the vulnerability.
     */
    public function down(): void {}
};
