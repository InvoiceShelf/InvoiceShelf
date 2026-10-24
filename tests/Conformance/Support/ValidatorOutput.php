<?php

namespace Tests\Conformance\Support;

use RuntimeException;
use SimpleXMLElement;

/**
 * A validator's stdout, parsed as its XML report. Output that is not a report
 * (missing jar, unreadable file, JVM that never started) raises instead of
 * being read as "not conformant".
 */
final class ValidatorOutput
{
    /**
     * Parse a validator's output, insisting on the root element its reports
     * carry.
     *
     * @throws RuntimeException when the output is not that report
     */
    public static function parse(string $output, string $rootElement, string $tool): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $report = simplexml_load_string(trim($output));
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($report === false || $report->getName() !== $rootElement) {
            throw new RuntimeException("{$tool} did not return a validation report:\n".trim($output));
        }

        return $report;
    }
}
