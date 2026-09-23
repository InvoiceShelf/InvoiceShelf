<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Delete the sendmail command the mail settings used to store.
 *
 * The sendmail path is run as a command, and the mail settings let an
 * administrator set it to anything. It now comes from MAIL_SENDMAIL_PATH
 * alone, so the stored value is never read again; this makes sure none is
 * left lying around to be read by mistake later.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->where('option', 'mail_sendmail_path')->delete();
    }

    /**
     * Irreversible by design: the stored command is the vulnerability.
     */
    public function down(): void {}
};
