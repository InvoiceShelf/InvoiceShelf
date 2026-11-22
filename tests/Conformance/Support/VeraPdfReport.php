<?php

namespace Tests\Conformance\Support;

use RuntimeException;
use SimpleXMLElement;

/**
 * veraPDF's verdict on the PDF/A conformance of a container.
 *
 * Embedding a file requires PDF/A-3, so the container is checked on its own
 * with the reference implementation rather than relying on Mustang's copy of
 * the same check. Run with `--format xml`, a file veraPDF cannot parse yields a
 * <taskException> and no <validationReport>, so conformance is read positively:
 * at least one file validated, all of them compliant, no job failed.
 *
 * @see https://docs.verapdf.org/cli/
 */
final class VeraPdfReport
{
    /**
     * @param  list<string>  $problems
     */
    private function __construct(
        private readonly bool $compliant,
        private readonly ?string $profileName,
        private readonly array $problems,
    ) {}

    /**
     * Read a report from what veraPDF printed.
     *
     * @throws RuntimeException when the output is not a report at all, which is
     *                          a broken validator run rather than a verdict
     */
    public static function fromOutput(string $output): self
    {
        $report = ValidatorOutput::parse($output, 'report', 'veraPDF');

        $summary = $report->batchSummary->validationReports ?? null;

        $compliant = (int) ($summary['compliant'] ?? 0);
        $nonCompliant = (int) ($summary['nonCompliant'] ?? 0);
        $failedJobs = (int) ($summary['failedJobs'] ?? 0);
        $failedToParse = (int) ($report->batchSummary['failedToParse'] ?? 0);

        return new self(
            $compliant > 0 && $nonCompliant === 0 && $failedJobs === 0 && $failedToParse === 0,
            self::profileNameOf($report),
            self::problemsOf($report),
        );
    }

    /**
     * Whether every file in the batch was validated and found conformant.
     */
    public function isCompliant(): bool
    {
        return $this->compliant;
    }

    /**
     * The profile the file was validated against, e.g. `PDF/A-3b validation
     * profile`. Null when nothing was validated.
     */
    public function profileName(): ?string
    {
        return $this->profileName;
    }

    /**
     * Every broken rule and every job that never got as far as a verdict.
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
            "veraPDF reported the container as %s (profile: %s).\n%s",
            $this->compliant ? 'compliant' : 'not compliant',
            $this->profileName ?? 'none — nothing was validated',
            $lines === [] ? '  (no failed rules reported)' : implode("\n", $lines),
        );
    }

    /**
     * The profile of the first file validated, if any was.
     */
    private static function profileNameOf(SimpleXMLElement $report): ?string
    {
        foreach ($report->xpath('//validationReport') ?: [] as $validation) {
            $name = trim((string) ($validation['profileName'] ?? ''));

            if ($name !== '') {
                return $name;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function problemsOf(SimpleXMLElement $report): array
    {
        $problems = [];

        foreach ($report->xpath('//rule[@status="failed"]') ?: [] as $rule) {
            $messages = array_map(
                static fn (SimpleXMLElement $check): string => trim((string) $check->errorMessage),
                $rule->xpath('check[@status="failed"]') ?: [],
            );

            $problems[] = sprintf(
                '%s clause %s test %s: %s — %s',
                (string) ($rule['specification'] ?? 'PDF/A'),
                (string) ($rule['clause'] ?? '?'),
                (string) ($rule['testNumber'] ?? '?'),
                trim((string) $rule->description),
                implode('; ', array_filter($messages)),
            );
        }

        // A job that ended in an exception never reached a verdict; without this
        // an unparsable file would be reported as "not compliant" with nothing
        // to explain it.
        foreach ($report->xpath('//taskException') ?: [] as $exception) {
            $problems[] = sprintf(
                '%s task failed: %s',
                (string) ($exception['type'] ?? 'unknown'),
                trim((string) $exception->exceptionMessage),
            );
        }

        return $problems;
    }
}
