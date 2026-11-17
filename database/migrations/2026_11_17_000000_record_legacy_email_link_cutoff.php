<?php

use App\Http\Controllers\V1\Customer\LegacyEmailLinkController;
use App\Models\EmailLog;
use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Remembers the newest email that may have gone out with an old link, so that
 * LegacyEmailLinkController answers for no email sent from now on.
 *
 * Emails sent before 2.4.4 carried old links. Those sent between 2.4.4 and
 * this migration carried random ones but cannot be told apart, so they are
 * included; that is harmless, because an old link only ever sends the current
 * link to the address the email went to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Setting::setSetting(
            LegacyEmailLinkController::CUTOFF_SETTING,
            (int) EmailLog::query()->max('id')
        );
    }

    public function down(): void
    {
        Setting::query()->where('option', LegacyEmailLinkController::CUTOFF_SETTING)->delete();
    }
};
