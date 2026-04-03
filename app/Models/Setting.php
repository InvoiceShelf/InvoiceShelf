<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = ['option', 'value'];

    /**
     * Create or update a single application setting by key.
     */
    public static function setSetting(string $key, mixed $setting): void
    {
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

    /**
     * Bulk create or update application settings from a key-value array.
     */
    public static function setSettings(array $settings): void
    {
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

    /**
     * Retrieve a single setting value by key, or null if not found.
     */
    public static function getSetting(string $key): mixed
    {
        $setting = static::whereOption($key)->first();

        if ($setting) {
            return $setting->value;
        } else {
            return null;
        }
    }

    /**
     * Retrieve multiple settings as a key-value collection.
     */
    public static function getSettings(array $settings): Collection
    {
        return static::whereIn('option', $settings)
            ->get()->mapWithKeys(function ($item) {
                return [$item['option'] => $item['value']];
            });
    }
}
