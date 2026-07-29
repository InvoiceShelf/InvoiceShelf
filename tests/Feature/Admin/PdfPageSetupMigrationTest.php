<?php

use App\Models\Setting;

/**
 * Existing installs have paper size stored as one Gotenberg-only string. It has
 * to land on the new driver-neutral pair, or an operator who deliberately chose
 * Letter silently gets A4 back on the next release.
 */
function runPageSetupMigration(): void
{
    (require dirname(__DIR__, 3).'/database/migrations/2026_07_29_120000_move_gotenberg_papersize_to_shared_page_setup.php')->up();
}

test('an existing papersize is split into width and height', function () {
    Setting::setSetting('gotenberg_papersize', '8.5in 14in');

    runPageSetupMigration();

    expect(Setting::getSetting('pdf_paper_width'))->toBe('8.5in')
        ->and(Setting::getSetting('pdf_paper_height'))->toBe('14in');
});

test('the retired keys are removed', function () {
    Setting::setSettings([
        'gotenberg_papersize' => '210mm 297mm',
        'gotenberg_margins' => '10mm',
    ]);

    runPageSetupMigration();

    expect(Setting::getSetting('gotenberg_papersize'))->toBeNull()
        ->and(Setting::getSetting('gotenberg_margins'))->toBeNull();
});

/**
 * No stored value, or one that never matched the old format, leaves the new keys
 * unset so config/pdf.php's A4 default applies.
 */
test('an absent or unparseable papersize leaves the defaults in place', function (?string $stored) {
    if ($stored !== null) {
        Setting::setSetting('gotenberg_papersize', $stored);
    }

    runPageSetupMigration();

    expect(Setting::getSetting('pdf_paper_width'))->toBeNull();
})->with([
    'absent' => null,
    'single token' => 'a4',
    'empty' => '',
]);
