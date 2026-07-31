<?php

namespace App\Console\Commands;

use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Document\EstimateService;
use App\Services\Document\InvoiceService;
use App\Services\Document\PaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Renders each stock template through both PDF drivers and compares the result.
 *
 * The templates were authored against dompdf and Gotenberg was added later, so
 * "the PDF looks different" has been diagnosed by eye every time. Nothing in the
 * suite compares the two renderers, because asserting on PDF bytes is useless
 * and rendering needs a live Gotenberg. This measures the things that actually
 * matter -- page box and where the ink lands -- so a layout change is a number
 * rather than an impression.
 *
 * Word positions come from poppler's pdftotext, which is on most dev machines
 * but not in the app container. Without it the command still compares page
 * geometry and says what it could not check.
 */
class ComparePdfDriversCommand extends Command
{
    protected $signature = 'pdf:compare
        {--template=* : Limit to these templates, e.g. --template=invoice1}
        {--tolerance=2 : Allowed difference in points before a row is flagged}';

    protected $description = 'Render every stock template through both PDF drivers and compare the output';

    public function handle(): int
    {
        if (! $this->gotenbergConfigured()) {
            $this->components->error(
                'Gotenberg is not reachable at '.config('pdf.connections.gotenberg.host').
                '. Set GOTENBERG_HOST (and GOTENBERG_ALLOWED_PRIVATE_HOST) and try again.'
            );

            return self::FAILURE;
        }

        $documents = $this->documents();

        if ($documents === []) {
            $this->components->error('No seeded documents to render. Run the demo seeder first.');

            return self::FAILURE;
        }

        $tolerance = (float) $this->option('tolerance');
        $only = $this->option('template');
        $rows = [];
        $worst = 0.0;

        // Switching a document between designs has to be persisted to be seen by
        // the services, so the whole run happens inside a transaction that is
        // always rolled back. Nothing here should change the operator's data.
        DB::beginTransaction();

        try {
            $this->compare($documents, $only, $tolerance, $rows, $worst);
        } finally {
            DB::rollBack();
        }

        $this->table(['template', 'dompdf', 'gotenberg', 'ink delta', ''], $rows);

        if (! $this->hasPdfToText()) {
            $this->components->warn('pdftotext not found, so only page geometry was compared. Install poppler-utils for ink positions.');
        }

        return $worst <= $tolerance ? self::SUCCESS : self::FAILURE;
    }

    private function compare(array $documents, array $only, float $tolerance, array &$rows, float &$worst): void
    {
        foreach ($documents as [$label, $render]) {
            if ($only && ! in_array($label, $only, true)) {
                continue;
            }

            try {
                $a = $this->measure($render('dompdf'));
                $b = $this->measure($render('gotenberg'));
            } catch (\Throwable $e) {
                $rows[] = [$label, 'ERROR', substr($e->getMessage(), 0, 60), '', ''];

                continue;
            }

            $delta = $this->delta($a, $b);
            $worst = max($worst, $delta ?? 0.0);

            $rows[] = [
                $label,
                $this->describe($a),
                $this->describe($b),
                $delta === null ? 'n/a' : sprintf('%.1fpt', $delta),
                $delta === null ? '?' : ($delta <= $tolerance ? 'ok' : 'DIFFERS'),
            ];
        }
    }

    /**
     * One closure per template, rendering it through whichever driver is named.
     * Goes through the real services so the comparison exercises the same shared
     * view data and template resolution a request would.
     *
     * @return list<array{0: string, 1: callable(string): string}>
     */
    private function documents(): array
    {
        $documents = [];

        if ($invoice = Invoice::first()) {
            foreach (['invoice1', 'invoice2', 'invoice3'] as $template) {
                $documents[] = [$template, function (string $driver) use ($invoice, $template) {
                    // Persisted, not just assigned: InvoiceService re-reads the
                    // template with Invoice::find($id)->template_name, so an
                    // in-memory change is ignored and every row would compare the
                    // same design. Rolled back in handle().
                    $invoice->forceFill(['template_name' => $template])->saveQuietly();

                    return $this->withDriver($driver, fn () => app(InvoiceService::class)->getPdfData($invoice)->output());
                }];
            }
        }

        if ($estimate = Estimate::first()) {
            foreach (['estimate1', 'estimate2', 'estimate3'] as $template) {
                $documents[] = [$template, function (string $driver) use ($estimate, $template) {
                    $estimate->forceFill(['template_name' => $template])->saveQuietly();

                    return $this->withDriver($driver, fn () => app(EstimateService::class)->getPdfData($estimate)->output());
                }];
            }
        }

        if ($payment = Payment::first()) {
            $documents[] = ['payment', fn (string $driver) => $this->withDriver(
                $driver,
                fn () => app(PaymentService::class)->getPdfData($payment)->output()
            )];
        }

        return $documents;
    }

    private function withDriver(string $driver, callable $render): string
    {
        $previous = config('pdf.driver');
        $pageNumbers = config('pdf.page.page_numbers');

        Config::set('pdf.driver', $driver);

        // Page numbers are a Chromium capability with no dompdf equivalent, so
        // leaving them on guarantees a difference at the foot of every page and
        // drowns out the ones worth seeing. Turned off for the comparison.
        Config::set('pdf.page.page_numbers', false);

        try {
            return $render();
        } finally {
            Config::set('pdf.driver', $previous);
            Config::set('pdf.page.page_numbers', $pageNumbers);
        }
    }

    /**
     * Page box, page count, and the bounding box of all text on page one.
     *
     * @return array{width: float, height: float, pages: int, ink: ?array{0: float, 1: float, 2: float, 3: float}}
     */
    private function measure(string $pdf): array
    {
        $file = tempnam(sys_get_temp_dir(), 'pdfcmp').'.pdf';
        file_put_contents($file, $pdf);

        try {
            return [
                'width' => $this->pageDimension($pdf, 0),
                'height' => $this->pageDimension($pdf, 1),
                'pages' => max(1, preg_match_all('/\/Type\s*\/Page[^s]/', $pdf)),
                'ink' => $this->hasPdfToText() ? $this->inkBox($file) : null,
            ];
        } finally {
            @unlink($file);
        }
    }

    /**
     * Reads the first MediaBox out of the raw PDF, so page size needs no tooling.
     */
    private function pageDimension(string $pdf, int $index): float
    {
        if (preg_match('/MediaBox\s*\[\s*([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)/', $pdf, $m)) {
            return round((float) $m[3 + $index] - (float) $m[1 + $index], 1);
        }

        return 0.0;
    }

    /**
     * @return ?array{0: float, 1: float, 2: float, 3: float}
     */
    private function inkBox(string $file): ?array
    {
        $process = new Process(['pdftotext', '-bbox', '-f', '1', '-l', '1', $file, '-']);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        preg_match_all(
            '/<word xMin="([\d.]+)" yMin="([\d.]+)" xMax="([\d.]+)" yMax="([\d.]+)"/',
            $process->getOutput(),
            $words,
            PREG_SET_ORDER
        );

        if ($words === []) {
            return null;
        }

        return [
            min(array_map(fn ($w) => (float) $w[1], $words)),
            min(array_map(fn ($w) => (float) $w[2], $words)),
            max(array_map(fn ($w) => (float) $w[3], $words)),
            max(array_map(fn ($w) => (float) $w[4], $words)),
        ];
    }

    /**
     * The largest edge-to-edge disagreement between the two ink boxes. Null when
     * either side could not be measured.
     */
    private function delta(array $a, array $b): ?float
    {
        if ($a['ink'] === null || $b['ink'] === null) {
            return null;
        }

        return round(max(array_map(
            fn ($x, $y) => abs($x - $y),
            $a['ink'],
            $b['ink']
        )), 2);
    }

    private function describe(array $m): string
    {
        $size = sprintf('%.0fx%.0f', $m['width'], $m['height']);
        $pages = $m['pages'].'p';

        if ($m['ink'] === null) {
            return "{$size} {$pages}";
        }

        return sprintf('%s %s ink %.0f,%.0f-%.0f,%.0f', $size, $pages, ...$m['ink']);
    }

    private function hasPdfToText(): bool
    {
        static $available = null;

        if ($available === null) {
            $process = new Process(['which', 'pdftotext']);
            $process->run();
            $available = $process->isSuccessful();
        }

        return $available;
    }

    private function gotenbergConfigured(): bool
    {
        $host = rtrim((string) config('pdf.connections.gotenberg.host'), '/');

        if ($host === '') {
            return false;
        }

        $context = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);

        return @file_get_contents($host.'/health', false, $context) !== false;
    }
}
