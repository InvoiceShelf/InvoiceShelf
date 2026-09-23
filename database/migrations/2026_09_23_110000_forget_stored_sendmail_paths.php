<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Delete the sendmail commands that the mail settings forms used to store.
 *
 * The sendmail path is run as a command, and the forms let an administrator,
 * and for a company's own mail an owner, set it to anything. It now comes
 * from MAIL_SENDMAIL_PATH alone, so the stored values are never read again;
 * this makes sure none is left lying around to be read by mistake later.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->where('option', 'mail_sendmail_path')->delete();
        DB::table('company_settings')->where('option', 'company_mail_sendmail_path')->delete();
    }

    /**
     * Irreversible by design: the stored commands are the vulnerability.
     */
    public function down(): void {}
};
