<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = ['option', 'value'];

    /**
     * Eloquent has no DB resolver before the database service is bound (e.g. very early boot or mis-ordered module code).
     */
    private static function databaseUnavailable(): bool
    {
        return static::getConnectionResolver() === null;
    }

    public static function setSetting($key, $setting)
    {
        if (self::databaseUnavailable()) {
            return;
        }

        $old = self::whereOption($key)->first();

        if ($old) {
            $old->value = $setting;
            $old->save();

            return;
        }

        $set = new Setting;
        $set->option = $key;
        $set->value = $setting;
        $set->save();
    }

    public static function setSettings($settings)
    {
        if (self::databaseUnavailable()) {
            return;
        }

        foreach ($settings as $key => $value) {
            self::updateOrCreate(
                [
                    'option' => $key,
                ],
                [
                    'option' => $key,
                    'value' => $value,
                ]
            );
        }
    }

    public static function getSetting($key)
    {
        if (self::databaseUnavailable()) {
            return null;
        }

        $setting = static::whereOption($key)->first();

        if ($setting) {
            return $setting->value;
        } else {
            return null;
        }
    }

    public static function getSettings($settings)
    {
        if (self::databaseUnavailable()) {
            return collect();
        }

        return static::whereIn('option', $settings)
            ->get()->mapWithKeys(function ($item) {
                return [$item['option'] => $item['value']];
            });
    }
}
