<?php

namespace App\Support\Formatting;

use Carbon\Carbon;

/**
 * The date layouts a company may choose between.
 *
 * Every layout is held twice, as a PHP pattern for whatever the server renders
 * and as the moment.js pattern that matches it in the browser, so both sides
 * write a saved date the same way round. What the settings screen shows is
 * today's date put through the server pattern, in the company's own language.
 */
class DateFormatter
{
    /**
     * The offered layouts, server pattern beside its browser counterpart.
     *
     * KNOWN QUIRK: the moment pattern for 'Y/m/d' carries a leading space. It
     * has been handed to clients that way for long enough to count as part of
     * the payload, so it is left alone.
     *
     * @var array<int, array<string, string>>
     */
    protected static $formats = [
        ['carbon_format' => 'Y M d', 'moment_format' => 'YYYY MMM DD'],
        ['carbon_format' => 'd M Y', 'moment_format' => 'DD MMM YYYY'],
        ['carbon_format' => 'd/m/Y', 'moment_format' => 'DD/MM/YYYY'],
        ['carbon_format' => 'd.m.Y', 'moment_format' => 'DD.MM.YYYY'],
        ['carbon_format' => 'd-m-Y', 'moment_format' => 'DD-MM-YYYY'],
        ['carbon_format' => 'm/d/Y', 'moment_format' => 'MM/DD/YYYY'],
        ['carbon_format' => 'Y/m/d', 'moment_format' => ' YYYY/MM/DD'],
        ['carbon_format' => 'Y-m-d', 'moment_format' => 'YYYY-MM-DD'],
    ];

    /**
     * The browser pattern for an offered server pattern, or null when the
     * server pattern is not one of the offered layouts.
     */
    public static function momentFormatFor(string $carbonFormat): ?string
    {
        foreach (static::$formats as $layout) {
            if ($layout['carbon_format'] === $carbonFormat) {
                return $layout['moment_format'];
            }
        }

        return null;
    }

    public static function get_list()
    {
        return array_map(
            fn (array $layout) => [
                'display_date' => Carbon::now()->translatedFormat($layout['carbon_format']),
                'carbon_format_value' => $layout['carbon_format'],
                'moment_format_value' => $layout['moment_format'],
            ],
            static::$formats
        );
    }
}
