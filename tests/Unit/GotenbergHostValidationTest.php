<?php

use App\Http\Requests\PDFConfigurationRequest;
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
