<?php

namespace Tests\Conformance\Support;

use RuntimeException;
use SimpleXMLElement;

/**
 * Mustang CLI's verdict on a hybrid PDF: the embedded XML against the EN 16931
 * XSD and Schematron, plus the container and its Factur-X metadata.
 *
 * The report is XML on stdout with several <summary> elements (pdf, xml and the
 * document as a whole); the last one, directly under <validation>, is the
 * verdict. A PDF without embedded XML, such as the ordinary one the fallback
 * produces, has no <xml> section.
 *
 * @see https://www.mustangproject.org/commandline/
 */
final class MustangReport
{
    /**
     * @param  list<string>  $problems
     */
    private function __construct(
        private readonly bool $valid,
        private readonly ?string $profile,
        private readonly array $problems,
    ) {}

    /**
     * Read a report from what Mustang printed.
     *
     * @throws RuntimeException when the output is not a report at all, which is
     *                          a broken validator run rather than a verdict
     */
    public static function fromOutput(string $output): self
    {
        $report = ValidatorOutput::parse($output, 'validation', 'Mustang');

        return new self(
            (string) ($report->summary['status'] ?? '') === 'valid',
            self::profileOf($report),
            self::problemsOf($report),
        );
    }

    /**
     * Whether Mustang accepted the document — XML, container and metadata.
     */
    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * The specification identifier (BT-24) of the embedded XML, as Mustang read
     * it, or null when there was no XML to read.
     */
    public function profile(): ?string
    {
        return $this->profile;
    }

    /**
     * Everything Mustang complained about, ready to be read by a human.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        return $this->problems;
    }

    /**
     * The report as an assertion message.
     */
    public function summary(): string
    {
        $lines = array_map(static fn (string $problem): string => '  - '.$problem, $this->problems);

        return sprintf(
            "Mustang reported the hybrid PDF as %s (profile: %s).\n%s",
            $this->valid ? 'valid' : 'invalid',
            $this->profile ?? 'none — no XML was extracted',
            $lines === [] ? '  (no messages)' : implode("\n", $lines),
        );
    }

    /**
     * The profile of the embedded XML, absent when no XML was extracted.
     */
    private static function profileOf(SimpleXMLElement $report): ?string
    {
        $profile = trim((string) ($report->xml->info->profile ?? ''));

        return $profile === '' ? null : $profile;
    }

    /**
     * Every message the report carries, wherever it sits — the container
     * section, the XML section and the document level all report separately,
     * and any one of them can be the reason for a failed verdict.
     *
     * Notices are left out: Mustang emits them for rules of profiles the
     * document does not claim (XRechnung's, for a ZUGFeRD EN 16931 invoice) and
     * they do not affect the verdict.
     *
     * @return list<string>
     */
    private static function problemsOf(SimpleXMLElement $report): array
    {
        $problems = [];

        foreach ($report->xpath('//messages/*') ?: [] as $message) {
            if ($message->getName() === 'notice') {
                continue;
            }

            $problems[] = sprintf(
                '[%s %s] %s',
                $message->getName(),
                (string) ($message['type'] ?? '?'),
                trim((string) $message),
            );
        }

        return $problems;
    }
}
