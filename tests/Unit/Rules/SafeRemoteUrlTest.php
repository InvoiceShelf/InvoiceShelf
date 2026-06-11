<?php

use App\Rules\SafeRemoteUrl;
use Illuminate\Support\Facades\Validator;

test('rejects internal, private and reserved hosts', function (string $url) {
    expect(SafeRemoteUrl::isSafe($url))->toBeFalse();
})->with([
    'http://127.0.0.1',
    'http://169.254.169.254',   // cloud metadata
    'http://10.0.0.5',
    'http://192.168.1.1',
    'http://172.16.0.1',
    'http://100.64.0.1',        // CGNAT
    'http://[::1]',
    'http://0.0.0.0',
    'ftp://8.8.8.8',            // non-http scheme
    'not-a-url',
]);

test('allows public http(s) addresses', function (string $url) {
    expect(SafeRemoteUrl::isSafe($url))->toBeTrue();
})->with([
    'http://8.8.8.8',
    'https://1.1.1.1',
    'http://93.184.216.34',
]);

test('fails validation for an internal gotenberg host', function () {
    $validator = Validator::make(
        ['gotenberg_host' => 'http://169.254.169.254'],
        ['gotenberg_host' => [new SafeRemoteUrl]]
    );

    expect($validator->fails())->toBeTrue();
});

test('passes validation for a public gotenberg host', function () {
    $validator = Validator::make(
        ['gotenberg_host' => 'https://1.1.1.1'],
        ['gotenberg_host' => [new SafeRemoteUrl]]
    );

    expect($validator->fails())->toBeFalse();
});
