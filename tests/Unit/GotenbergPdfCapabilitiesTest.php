<?php

use App\Platform\Modules\Infrastructure\GotenbergPdfCapabilities;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvoiceShelf\Modules\Contracts\Host\PdfCapabilities;

/**
 * Factur-X needs Gotenberg 8.34 or newer with LibreOffice, so the full image
 * and not the -chromium one. The service is asked, and the answer cached.
 */
beforeEach(function () {
    Cache::flush();
    config([
        'pdf.driver' => 'gotenberg',
        'pdf.connections.gotenberg.host' => 'http://pdf:3000',
        'network.allowed_private_hosts.gotenberg' => ['http://pdf:3000'],
    ]);
});

function gotenbergAnswers(string $version, bool $libreOffice = true): void
{
    $details = ['chromium' => ['status' => 'up']] + ($libreOffice ? ['libreoffice' => ['status' => 'up']] : []);

    Http::fake([
        'pdf:3000/version' => Http::response($version),
        'pdf:3000/health' => Http::response(['status' => 'up', 'details' => $details]),
    ]);
}

test('the host binds the gotenberg capability check for modules', function () {
    expect(app(PdfCapabilities::class))->toBeInstanceOf(GotenbergPdfCapabilities::class);
});

test('a recent full gotenberg can embed factur-x', function () {
    gotenbergAnswers('8.37.0');

    expect((new GotenbergPdfCapabilities)->embedsFacturX())->toBeTrue();
});

test('gotenberg before 8.34 cannot', function () {
    gotenbergAnswers('8.33.1');

    expect((new GotenbergPdfCapabilities)->embedsFacturX())->toBeFalse();
});

test('the chromium-only image cannot convert to pdf/a', function () {
    gotenbergAnswers('8.37.0', libreOffice: false);

    expect((new GotenbergPdfCapabilities)->embedsFacturX())->toBeFalse();
});

test('dompdf cannot, and gotenberg is not asked', function () {
    config(['pdf.driver' => 'dompdf']);
    Http::fake();

    expect((new GotenbergPdfCapabilities)->embedsFacturX())->toBeFalse();
    Http::assertNothingSent();
});

test('the answer is cached', function () {
    gotenbergAnswers('8.37.0');
    $capabilities = new GotenbergPdfCapabilities;

    $capabilities->embedsFacturX();
    $capabilities->embedsFacturX();

    Http::assertSentCount(2);
});

test('an unreachable service reads as unable', function () {
    Http::fake(fn () => throw new ConnectionException('refused'));

    expect((new GotenbergPdfCapabilities)->embedsFacturX())->toBeFalse();
});

test('a private host the operator did not declare is never probed', function () {
    config(['pdf.connections.gotenberg.host' => 'http://169.254.169.254', 'network.allowed_private_hosts.gotenberg' => []]);
    Http::fake();

    expect((new GotenbergPdfCapabilities)->embedsFacturX())->toBeFalse();
    Http::assertNothingSent();
});
