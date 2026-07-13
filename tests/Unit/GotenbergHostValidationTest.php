<?php

use App\Http\Requests\PDFConfigurationRequest;
use App\Support\Pdf\GotenbergPdfDriver;
use Illuminate\Support\Facades\Validator;

test('gotenberg host rejects private, loopback and link-local addresses', function (string $url) {
    $rules = PDFConfigurationRequest::create('/', 'POST', ['pdf_driver' => 'gotenberg'])->rules();

    $validator = Validator::make(['gotenberg_host' => $url], ['gotenberg_host' => $rules['gotenberg_host']]);

    expect($validator->fails())->toBeTrue();
})->with([
    'http://127.0.0.1',
    'http://169.254.169.254',
    'http://10.0.0.5',
    'http://192.168.1.1',
]);

test('gotenberg host allows a public address', function () {
    $rules = PDFConfigurationRequest::create('/', 'POST', ['pdf_driver' => 'gotenberg'])->rules();

    $validator = Validator::make(['gotenberg_host' => 'http://8.8.8.8'], ['gotenberg_host' => $rules['gotenberg_host']]);

    expect($validator->errors()->has('gotenberg_host'))->toBeFalse();
});

test('gotenberg host accepts a private address when allow_private_host is enabled', function (string $url) {
    $rules = PDFConfigurationRequest::create('/', 'POST', [
        'pdf_driver' => 'gotenberg',
        'gotenberg_allow_private_host' => true,
    ])->rules();

    $validator = Validator::make(['gotenberg_host' => $url], ['gotenberg_host' => $rules['gotenberg_host']]);

    expect($validator->errors()->has('gotenberg_host'))->toBeFalse();
})->with([
    'http://10.0.0.1:3000',
    'http://pdf:3000',
    'http://192.168.1.50:3000',
]);

test('gotenberg driver skips ssrf guard when allow_private_host config is true', function () {
    config([
        'pdf.connections.gotenberg.host' => 'http://10.0.0.1:3000',
        'pdf.connections.gotenberg.papersize' => '210mm 297mm',
        'pdf.connections.gotenberg.allow_private_host' => true,
    ]);

    $driver = new GotenbergPdfDriver;

    try {
        $driver->loadView('app.pdf.invoice.invoice1');
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())->not->toContain('Invalid Gotenberg host');
    } catch (Throwable) {
        // Any other exception (e.g. connection failure) is expected — the guard was not triggered.
    }
});
