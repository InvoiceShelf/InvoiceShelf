<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rows the inherited list filed under codes that are not ISO 3166-1, as
     * old code => [ISO code, old name, name]. Addresses keep their country id,
     * so recoding the row moves every address with it.
     */
    private const RECODED = [
        'XM' => ['IM', 'Man (Isle of)', 'Isle of Man'],
        'XJ' => ['JE', 'Jersey', 'Jersey'],
        'XU' => ['GG', 'Guernsey and Alderney', 'Guernsey'],
        'TP' => ['TL', 'East Timor', 'Timor-Leste'],
    ];

    /**
     * Names the inherited list spelled its own way, as code => [old name,
     * name]. The new names are the CLDR English ones, which is what 3.x
     * already prints on documents for these codes.
     */
    private const RENAMED = [
        'AG' => ['Antigua And Barbuda', 'Antigua & Barbuda'],
        'BS' => ['Bahamas The', 'Bahamas'],
        'BA' => ['Bosnia and Herzegovina', 'Bosnia & Herzegovina'],
        'CG' => ['Congo', 'Congo - Brazzaville'],
        'CD' => ['Congo The Democratic Republic Of The', 'Congo - Kinshasa'],
        'CI' => ['Cote D Ivoire (Ivory Coast)', 'Côte d’Ivoire'],
        'HR' => ['Croatia (Hrvatska)', 'Croatia'],
        'CZ' => ['Czech Republic', 'Czechia'],
        'FJ' => ['Fiji Islands', 'Fiji'],
        'GM' => ['Gambia The', 'Gambia'],
        'HM' => ['Heard and McDonald Islands', 'Heard & McDonald Islands'],
        'HK' => ['Hong Kong S.A.R.', 'Hong Kong SAR China'],
        'KP' => ['Korea North', 'North Korea'],
        'KR' => ['Korea South', 'South Korea'],
        'MO' => ['Macau S.A.R.', 'Macao SAR China'],
        'MK' => ['Macedonia', 'North Macedonia'],
        'MM' => ['Myanmar', 'Myanmar (Burma)'],
        'PS' => ['Palestinian Territory Occupied', 'Palestinian Territories'],
        'PG' => ['Papua new Guinea', 'Papua New Guinea'],
        'PN' => ['Pitcairn Island', 'Pitcairn Islands'],
        'RE' => ['Reunion', 'Réunion'],
        'SH' => ['Saint Helena', 'St. Helena'],
        'KN' => ['Saint Kitts And Nevis', 'St. Kitts & Nevis'],
        'LC' => ['Saint Lucia', 'St. Lucia'],
        'PM' => ['Saint Pierre and Miquelon', 'St. Pierre & Miquelon'],
        'VC' => ['Saint Vincent And The Grenadines', 'St. Vincent & Grenadines'],
        'ST' => ['Sao Tome and Principe', 'São Tomé & Príncipe'],
        'GS' => ['South Georgia', 'South Georgia & South Sandwich Islands'],
        'SJ' => ['Svalbard And Jan Mayen Islands', 'Svalbard & Jan Mayen'],
        'SZ' => ['Swaziland', 'Eswatini'],
        'TT' => ['Trinidad And Tobago', 'Trinidad & Tobago'],
        'TR' => ['Turkey', 'Türkiye'],
        'TC' => ['Turks And Caicos Islands', 'Turks & Caicos Islands'],
        'UM' => ['United States Minor Outlying Islands', 'U.S. Outlying Islands'],
        'VA' => ['Vatican City State (Holy See)', 'Vatican City'],
        'VG' => ['Virgin Islands (British)', 'British Virgin Islands'],
        'VI' => ['Virgin Islands (US)', 'U.S. Virgin Islands'],
        'WF' => ['Wallis And Futuna Islands', 'Wallis & Futuna'],
    ];

    /**
     * Countries the inherited list never had, as code => [name, phone code].
     */
    private const ADDED = [
        'AX' => ['Åland Islands', 358],
        'BL' => ['St. Barthélemy', 590],
        'BQ' => ['Caribbean Netherlands', 599],
        'CW' => ['Curaçao', 599],
        'ME' => ['Montenegro', 382],
        'MF' => ['St. Martin', 590],
        'SX' => ['Sint Maarten', 1721],
    ];

    /**
     * Bring the country list of an existing install up to ISO 3166-1 (#957).
     *
     * The list inherited from Crater filed the Isle of Man, Jersey, Guernsey
     * and Timor-Leste under made-up codes (XM, XJ, XU, TP), which the CLDR
     * catalogue does not know: their documents fell back to the stored names,
     * "Man (Isle of)" among them, and an e-invoice could not name them. Those
     * rows are recoded in place, seven current countries are added, and the
     * old house spellings ("Korea South") become the CLDR English names the
     * country pickers show.
     *
     * A name is replaced only while it is still the one the list shipped with,
     * so a name an owner corrected by hand stays. Rows nobody can map (AN, YU,
     * XA, XG) stay too: addresses may point at them. A fresh install is left
     * alone; the seeder that runs after migrations writes the corrected list.
     *
     * New rows get explicit ids above the highest one, because the seeder
     * writes explicit ids and never advanced PostgreSQL's sequence.
     */
    public function up(): void
    {
        if (! DB::table('countries')->exists()) {
            return;
        }

        foreach (self::RECODED as $oldCode => [$code, $oldName, $name]) {
            if (DB::table('countries')->where('code', $code)->exists()) {
                continue;
            }

            DB::table('countries')->where('code', $oldCode)->update(['code' => $code]);
            DB::table('countries')->where('code', $code)->where('name', $oldName)->update(['name' => $name]);
        }

        foreach (self::RENAMED as $code => [$oldName, $name]) {
            DB::table('countries')->where('code', $code)->where('name', $oldName)->update(['name' => $name]);
        }

        foreach (self::ADDED as $code => [$name, $phoneCode]) {
            if (DB::table('countries')->where('code', $code)->exists()) {
                continue;
            }

            DB::table('countries')->insert([
                'id' => (int) DB::table('countries')->max('id') + 1,
                'code' => $code,
                'name' => $name,
                'phonecode' => $phoneCode,
            ]);
        }
    }

    /**
     * Not reversed: the old codes are not ISO codes, and putting them back
     * would only break the documents and e-invoices of those countries again.
     */
    public function down(): void {}
};
