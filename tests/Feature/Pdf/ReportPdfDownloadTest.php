<?php

use App\Domains\Accounts\Models\User;
use App\Platform\Pdf\Facades\Pdf;
use App\Platform\Pdf\Rendering\PdfPageSetup;
use App\Platform\Pdf\Rendering\ResponseStream;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\get;

/**
 * The five report controllers are the only callers of ->download(), and
 * GotenbergPdfResponse never had that method. Selecting the Gotenberg driver
 * therefore turned every report download into a fatal undefined-method error,
 * while the same page streamed fine. Nothing caught it because the factory
 * returned the vendor dompdf wrapper for one driver and a bespoke class for the
 * other, with no shared type between them.
 *
 * These run against dompdf so they need no Gotenberg service; the contract that
 * keeps the two in step is asserted in tests/Unit/PdfDriverContractTest.php.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->company = $user->companies()->first();

    // Bouncer scopes abilities by company, so `view report` only resolves with
    // the company header set.
    $this->withHeaders(['company' => $this->company->id]);

    Sanctum::actingAs($user, ['*']);

    config(['pdf.driver' => 'dompdf']);
});

dataset('reports', [
    'sales/customers',
    'sales/items',
    'expenses',
    'tax-summary',
    'profit-loss',
    'purchases',
]);

function reportUrl(string $report, string $hash, string $extra = ''): string
{
    return "/reports/{$report}/{$hash}?from_date=2020-01-01&to_date=2030-12-31{$extra}";
}

test('every report streams a pdf', function (string $report) {
    $response = get(reportUrl($report, $this->company->unique_hash));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect($response->getContent())->toStartWith('%PDF-');
})->with('reports');

test('every report can be downloaded as an attachment', function (string $report) {
    $response = get(reportUrl($report, $this->company->unique_hash, '&download=true'));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('attachment');
    expect($response->getContent())->toStartWith('%PDF-');
})->with('reports');

/**
 * The report templates carry no inset of their own and were drawn against
 * dompdf's built-in 1.2cm margin, which stopped applying once the driver began
 * injecting an @page rule from config (where documents deliberately default to
 * 0). Every report route therefore has to ask for the report page explicitly,
 * and a page margin rather than body padding: reports run to several pages, and
 * only a page margin repeats on each one.
 */
test('every report route renders at the report page setup', function (string $report) {
    config([
        'pdf.page.margin_top' => '0',
        'pdf.page.margin_right' => '0',
        'pdf.page.margin_bottom' => '0',
        'pdf.page.margin_left' => '0',
        'pdf.page.report_margin' => '1.2cm',
    ]);

    $stream = Mockery::mock(ResponseStream::class);
    $stream->shouldReceive('stream')->andReturn(new Response('%PDF-'));

    Pdf::shouldReceive('loadView')
        ->once()
        // Compared on the margins rather than by identity: the setup is built
        // per render, so no two calls ever share an instance.
        ->withArgs(function (string $template, array $metadata, ?PdfPageSetup $page) {
            return $page instanceof PdfPageSetup
                && $page->marginCss() === PdfPageSetup::forReports()->marginCss()
                && $page->marginCss() === '1.2cm 1.2cm 1.2cm 1.2cm';
        })
        ->andReturn($stream);

    get(reportUrl($report, $this->company->unique_hash))->assertOk();
})->with('reports');
