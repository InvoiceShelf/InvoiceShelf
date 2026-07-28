<?php

use App\Http\Requests\PDFConfigurationRequest;
use App\Support\Pdf\GotenbergHostPolicy;
use App\Support\Pdf\GotenbergPdfDriver;
use Illuminate\Support\Facades\Validator;

/**
 * Build the gotenberg_host rules the way the form request would for a given
 * submitted host, then validate that host against them.
 */
function validateGotenbergHost(string $url): Illuminate\Validation\Validator
{
    $rules = PDFConfigurationRequest::create('/', 'POST', [
        'pdf_driver' => 'gotenberg',
        'gotenberg_host' => $url,
    ])->rules();

    return Validator::make(['gotenberg_host' => $url], ['gotenberg_host' => $rules['gotenberg_host']]);
}

test('gotenberg host rejects private, loopback and link-local addresses', function (string $url) {
    expect(validateGotenbergHost($url)->fails())->toBeTrue();
})->with([
    'http://127.0.0.1',
    'http://169.254.169.254',
    'http://10.0.0.5',
    'http://192.168.1.1',
]);

test('gotenberg host allows a public address', function () {
    expect(validateGotenbergHost('http://8.8.8.8')->errors()->has('gotenberg_host'))->toBeFalse();
});

test('gotenberg host accepts the private host declared in the environment', function () {
    config(['pdf.connections.gotenberg.allowed_private_host' => 'http://10.0.0.5:3000']);

    expect(validateGotenbergHost('http://10.0.0.5:3000')->errors()->has('gotenberg_host'))->toBeFalse();
});

/**
 * The point of naming the host rather than flipping a boolean: declaring one
 * private host must not open the guard for any other. Without this, an operator
 * who enables the sidecar also hands a super admin the ability to repoint the
 * setting at a cloud metadata endpoint and read the response back as a "PDF".
 */
test('declaring one private host does not exempt any other', function (string $url) {
    config(['pdf.connections.gotenberg.allowed_private_host' => 'http://pdf:3000']);

    expect(validateGotenbergHost($url)->fails())->toBeTrue();
})->with([
    'http://169.254.169.254',
    'http://127.0.0.1:3000',
    'http://10.0.0.5:3000',
]);

test('an unset allowlist exempts nothing', function () {
    config(['pdf.connections.gotenberg.allowed_private_host' => null]);

    expect(validateGotenbergHost('http://10.0.0.5:3000')->fails())->toBeTrue();
});

test('the declared host is matched ignoring case and trailing slash', function (string $configured, string $submitted) {
    config(['pdf.connections.gotenberg.allowed_private_host' => $configured]);

    expect(GotenbergHostPolicy::isExemptFromPrivateNetworkGuard($submitted))->toBeTrue();
})->with([
    ['http://pdf:3000', 'http://pdf:3000/'],
    ['http://pdf:3000/', 'http://pdf:3000'],
    ['HTTP://PDF:3000', 'http://pdf:3000'],
    ['  http://pdf:3000  ', 'http://pdf:3000'],
]);

test('the policy rejects hosts that differ in any meaningful part', function (string $submitted) {
    config(['pdf.connections.gotenberg.allowed_private_host' => 'http://pdf:3000']);

    expect(GotenbergHostPolicy::isExemptFromPrivateNetworkGuard($submitted))->toBeFalse();
})->with([
    'http://pdf:3001',
    'https://pdf:3000',
    'http://pdf',
    'http://other:3000',
    'http://pdf:3000/render',
    'not a url',
    '',
]);

/**
 * The driver guard is the authoritative layer — it re-checks at request time, so
 * it has to agree with the validation rule. Asserted on the blocking path only:
 * it throws before any HTTP call is attempted, so the test never touches the
 * network.
 */
test('gotenberg driver still blocks a private host that was not declared', function () {
    config([
        'pdf.connections.gotenberg.host' => 'http://169.254.169.254',
        'pdf.connections.gotenberg.papersize' => '210mm 297mm',
        'pdf.connections.gotenberg.allowed_private_host' => 'http://pdf:3000',
    ]);

    expect(fn () => (new GotenbergPdfDriver)->loadView('app.pdf.invoice.invoice1'))
        ->toThrow(InvalidArgumentException::class, 'Invalid Gotenberg host');
});
