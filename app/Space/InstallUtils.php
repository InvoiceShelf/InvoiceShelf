<?php

namespace App\Space;

use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\File;

class InstallUtils
{
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
}
