<?php

namespace App\Platform\Operations\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * One row of the instance-wide configuration table.
 *
 * The interesting surface is static: the installer, the updater, seeders,
 * migrations and the platform services all treat this class as "the global
 * settings store" rather than as an entity they hydrate and pass around.
 */
class Setting extends Model
{
    use HasFactory;

    protected $table = 'settings';

    protected $fillable = ['option', 'value'];

    /**
     * The settings the browser shell paints with: the only ones the bootstrap
     * sends, and the only ones the generic settings endpoint reads or writes.
     *
     * Deliberately an allow-list. The rest of this table is credentials, the
     * installer's and updater's own state, or configuration that an endpoint
     * of its own validates (mail, PDF, file disks).
     */
    public const SHELL_SETTINGS = [
        'admin_portal_theme',
        'admin_portal_logo',
        'login_page_logo',
        'login_page_heading',
        'login_page_description',
        'admin_page_title',
        'copyright_text',
        'save_pdf_to_disk',
        'show_sidebar_group_labels',
    ];

    /**
     * Store one option. An option that already has a row is overwritten.
     */
    public static function setSetting(string $key, mixed $setting): void
    {
        static::writeOption($key, $setting);
    }

    /**
     * Store a batch of options, each one following the single-key write rule.
     *
     * @param  array<string, mixed>  $settings
     */
    public static function setSettings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            static::writeOption($key, $value);
        }
    }

    /**
     * Read one option. Keys that were never stored read as null.
     */
    public static function getSetting(string $key): mixed
    {
        return static::query()
            ->where('option', $key)
            ->value('value');
    }

    /**
     * Read several options at once, as an option => value map.
     *
     * Keys without a row are left out of the map entirely: callers get a
     * shorter map, never a null placeholder.
     *
     * @param  array<int, string>  $settings
     * @return Collection<string, mixed>
     */
    public static function getSettings(array $settings): Collection
    {
        return static::query()
            ->whereIn('option', $settings)
            ->pluck('value', 'option');
    }

    /**
     * The shared write rule behind both public writers: update in place when
     * the option is already known, insert it otherwise.
     */
    private static function writeOption(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['option' => $key], ['value' => $value]);
    }
}
