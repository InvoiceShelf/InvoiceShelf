<?php

use Tests\Conformance\Support\MustangReport;
use Tests\Conformance\Support\VeraPdfReport;

/**
 * Parsing of the Mustang and veraPDF reports, against reports the real tools
 * produced (only the file names were rewritten):
 *
 * - `mustang-*.xml`: Mustang CLI 2.25.0 (`--action validate --source <pdf>`) on
 *   the ZUGFeRD samples `EN16931_Einfach.pdf`, `XMLinvalidV2PDF.pdf` and
 *   `EmptyPDFA1.pdf`.
 * - `verapdf-*.xml`: veraPDF 1.30.2 (`-f 3b --format xml <pdf>`) on a conformant
 *   PDF/A-3b, a non-conformant one and a file that is not a PDF.
 *
 * Two cases need care: a plain PDF with no XML must not pass as an e-invoice,
 * and a veraPDF batch in which nothing was validated must not count as
 * compliant.
 *
 * @see tests/Conformance/HybridPdfConformanceTest.php
 */
function conformanceReportFixture(string $name): string
{
    return (string) file_get_contents(base_path("tests/Conformance/fixtures/{$name}.xml"));
}

test('mustang reports a conformant zugferd pdf as valid', function () {
    $report = MustangReport::fromOutput(conformanceReportFixture('mustang-valid'));

    expect($report->isValid())->toBeTrue()
        ->and($report->profile())->toBe('urn:cen.eu:en16931:2017')
        ->and($report->problems())->toBeEmpty();
});

test('mustang names what makes a pdf non-conformant', function () {
    $report = MustangReport::fromOutput(conformanceReportFixture('mustang-invalid'));

    expect($report->isValid())->toBeFalse()
        ->and($report->problems())->not->toBeEmpty();

    expect($report->summary())
        ->toContain('schema validation fails')
        ->toContain('Unsupported profile type');
});

/**
 * An ordinary invoice PDF without XML: non-conformant, and the report has no
 * profile and no <xml> section.
 */
test('mustang rejects a plain pdf that carries no e-invoice', function () {
    $report = MustangReport::fromOutput(conformanceReportFixture('mustang-plain-pdf'));

    expect($report->isValid())->toBeFalse()
        ->and($report->profile())->toBeNull()
        ->and($report->summary())
        ->toContain('XML could not be extracted')
        ->toContain('Not a PDF/A-3');
});

test('output that is not a mustang report is an error, not a verdict', function () {
    expect(fn () => MustangReport::fromOutput('Error: Unable to access jarfile Mustang-CLI.jar'))
        ->toThrow(RuntimeException::class, 'Unable to access jarfile');
});

test('verapdf reports a pdf/a-3b file as compliant', function () {
    $report = VeraPdfReport::fromOutput(conformanceReportFixture('verapdf-compliant'));

    expect($report->isCompliant())->toBeTrue()
        ->and($report->profileName())->toBe('PDF/A-3b validation profile')
        ->and($report->problems())->toBeEmpty();
});

test('verapdf names the pdf/a rules a file breaks', function () {
    $report = VeraPdfReport::fromOutput(conformanceReportFixture('verapdf-non-compliant'));

    expect($report->isCompliant())->toBeFalse();

    expect($report->summary())
        ->toContain('6.6.4')
        ->toContain('is 1 instead of 3 for PDF/A-3 conforming file');
});

/**
 * A batch in which nothing was validated reports no non-compliant file, so
 * conformance has to be read positively: an unvalidated batch is non-conformant.
 */
test('verapdf finding nothing to validate is not conformance', function () {
    $report = VeraPdfReport::fromOutput(conformanceReportFixture('verapdf-unparsable'));

    expect($report->isCompliant())->toBeFalse()
        ->and($report->summary())->toContain("Couldn't parse stream");
});

test('output that is not a verapdf report is an error, not a verdict', function () {
    expect(fn () => VeraPdfReport::fromOutput('verapdf: command not found'))
        ->toThrow(RuntimeException::class, 'command not found');
});
