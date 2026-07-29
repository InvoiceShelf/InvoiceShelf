<?php

use App\Models\User;
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
