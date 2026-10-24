<?php

namespace Tests\Conformance\Support;

use App\Platform\Pdf\Rendering\FacturXAttachment;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Where the conformance tests find their tools: a Gotenberg service, Mustang CLI
 * and veraPDF, each named by an environment variable.
 *
 * A missing variable fails the run instead of skipping the test, so the suite
 * cannot pass without having validated anything. The CI job is in
 * .github/workflows/pdf-conformance.yaml and the same variables reproduce
 * it locally.
 */
final class ConformanceEnvironment
{
    /**
     * How long a validator may take. Both start a JVM and Mustang compiles the
     * Schematron XSLTs of every profile on first use, so this is generous.
     */
    private const VALIDATOR_TIMEOUT_SECONDS = 600;

    /**
     * The Gotenberg service the hybrid PDF is built by, e.g.
     * `http://localhost:3000`.
     */
    public static function gotenbergHost(): string
    {
        return self::required(
            'CONFORMANCE_GOTENBERG_HOST',
            'the base URL of a running Gotenberg >= 8.34, e.g. http://localhost:3000',
        );
    }

    /**
     * Where the generated hybrid PDF is written, so a failing run can be
     * downloaded from the CI job and opened in a validator by hand.
     */
    public static function artifactDirectory(): string
    {
        $directory = getenv('CONFORMANCE_ARTIFACT_DIR') ?: storage_path('app/conformance');

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("Cannot create the conformance artifact directory {$directory}.");
        }

        return rtrim($directory, '/');
    }

    /**
     * Mustang's verdict on a hybrid PDF: the embedded XML against the EN 16931
     * XSD and Schematron, plus the container and its Factur-X XMP metadata.
     */
    public static function validateWithMustang(string $pdf): MustangReport
    {
        $jar = self::requiredFile(
            'CONFORMANCE_MUSTANG_JAR',
            'the path to Mustang-CLI-<version>.jar (https://github.com/ZUGFeRD/mustangproject/releases)',
        );

        $java = getenv('CONFORMANCE_JAVA_BIN') ?: 'java';

        return MustangReport::fromOutput(self::run(
            [$java, '-jar', $jar, '--action', 'validate', '--source', $pdf],
            'Mustang',
        ));
    }

    /**
     * veraPDF's verdict on the container, independently of Mustang's embedded
     * copy of it.
     */
    public static function validateWithVeraPdf(string $pdf): VeraPdfReport
    {
        $binary = self::requiredFile(
            'CONFORMANCE_VERAPDF_BIN',
            'the path to the veraPDF CLI (https://software.verapdf.org/rel/verapdf-installer.zip)',
        );

        return VeraPdfReport::fromOutput(self::run(
            [$binary, '-f', self::veraPdfFlavour(), '--format', 'xml', $pdf],
            'veraPDF',
        ));
    }

    /**
     * The flavour veraPDF is asked to validate against, taken from the
     * conformance the hybrid PDF is built to rather than restated here:
     * `PDF/A-3b` is veraPDF's `3b`.
     */
    private static function veraPdfFlavour(): string
    {
        return strtolower(str_replace('PDF/A-', '', FacturXAttachment::PDFA_CONFORMANCE));
    }

    /**
     * Run a validator and return its report.
     *
     * The exit code is deliberately ignored: both tools signal "not conformant"
     * with a non-zero status, which is a verdict and not a failure. Empty output
     * is the failure — that is a tool which never ran.
     *
     * @param  list<string>  $command
     */
    private static function run(array $command, string $tool): string
    {
        $process = new Process($command, base_path(), null, null, self::VALIDATOR_TIMEOUT_SECONDS);
        $process->run();

        $output = $process->getOutput();

        if (trim($output) === '') {
            throw new RuntimeException(sprintf(
                "%s produced no report (exit code %s):\n%s",
                $tool,
                $process->getExitCode() ?? 'none',
                trim($process->getErrorOutput()) ?: '(no output)',
            ));
        }

        return $output;
    }

    /**
     * An environment variable that must be set.
     */
    private static function required(string $variable, string $expected): string
    {
        $value = getenv($variable);

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException("{$variable} is not set. It must name {$expected}.");
        }

        return trim($value);
    }

    /**
     * An environment variable that must name a file that is there.
     */
    private static function requiredFile(string $variable, string $expected): string
    {
        $value = self::required($variable, $expected);

        if (! is_file($value)) {
            throw new RuntimeException("{$variable} points at {$value}, which does not exist. It must name {$expected}.");
        }

        return $value;
    }
}
