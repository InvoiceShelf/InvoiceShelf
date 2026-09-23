<?php

namespace App\Platform\Operations\Installation\Application;

use App\Platform\Operations\Installation\Authentication\InstallWizardAuth;
use App\Platform\Operations\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;

class InstallationState
{
    /**
     * The value of the profile_complete setting once the install is done.
     */
    public const COMPLETED = 'COMPLETED';

    /**
     * Check if database is created
     *
     * @return bool
     */
    public static function isDbCreated()
    {
        return self::tableExists('users');
    }

    /**
     * Check if database is created
     *
     * @return bool|int|string
     */
    public static function tableExists($table)
    {
        static $cache = [];

        if (isset($cache[$table])) {
            return $cache[$table];
        }

        try {
            $flag = \Schema::hasTable($table);
        } catch (QueryException|\Exception $e) {
            $flag = false;
        }

        $cache[$table] = $flag;

        return $cache[$table];
    }

    /**
     * Set the app version
     *
     * @return void
     */
    public static function setCurrentVersion()
    {
        $version = preg_replace('~[\r\n]+~', '', File::get(base_path('version.md')));
        if (! $version) {
            return;
        }
        Setting::setSetting('version', $version);
    }

    /**
     * Mark the installation finished: the installer closes, and the tokens it
     * handed the setup wizard stop working, since nothing else ever revokes
     * them.
     */
    public static function complete(): void
    {
        Setting::setSetting('profile_complete', self::COMPLETED);

        Sanctum::$personalAccessTokenModel::query()
            ->where('name', InstallWizardAuth::TOKEN_NAME)
            ->delete();
    }

    /**
     * Whether the installation has finished, read from the database each time
     * rather than through the per-process table probe above, so a process that
     * started before the schema existed still gets the right answer.
     */
    public static function isComplete(): bool
    {
        try {
            return Setting::getSetting('profile_complete') === self::COMPLETED;
        } catch (\Throwable) {
            return false;
        }
    }
}
