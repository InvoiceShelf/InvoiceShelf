<?php

use App\Rules\Base64Mime;
use Illuminate\Support\Facades\Validator;

// A 1x1 PNG and a 1x1 JPEG, as the browser would send them
const BASE64_MIME_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
const BASE64_MIME_JPEG = '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=';

function base64MimeUpload(string $name, string $mime, string $payload): string
{
    return json_encode(['name' => $name, 'data' => "data:{$mime};base64,{$payload}"]);
}

function base64MimePasses(string $value): bool
{
    return Validator::make(['logo' => $value], ['logo' => [new Base64Mime(['gif', 'jpg', 'png'])]])->passes();
}

test('accepts an image named for its type', function (string $name, string $mime, string $payload) {
    expect(base64MimePasses(base64MimeUpload($name, $mime, $payload)))->toBeTrue();
})->with([
    'png' => ['logo.png', 'image/png', BASE64_MIME_PNG],
    'upper-case extension' => ['LOGO.PNG', 'image/png', BASE64_MIME_PNG],
    'jpeg spelled out' => ['photo.jpeg', 'image/jpeg', BASE64_MIME_JPEG],
    'jpg' => ['photo.jpg', 'image/jpeg', BASE64_MIME_JPEG],
]);

// The name becomes the stored file's name, so an image payload must not carry
// a name that would be served as a page
test('refuses an image payload under a name of another type', function (string $name) {
    expect(base64MimePasses(base64MimeUpload($name, 'image/png', BASE64_MIME_PNG)))->toBeFalse();
})->with(['page.html', 'page.htm', 'image.svg', 'script.php', 'no-extension']);

test('refuses bytes that are not an accepted image, whatever the name', function () {
    $html = base64_encode('<html><script>alert(document.cookie)</script></html>');

    expect(base64MimePasses(base64MimeUpload('logo.png', 'image/png', $html)))->toBeFalse();
});

test('refuses a malformed envelope', function (string $value) {
    expect(base64MimePasses($value))->toBeFalse();
})->with([
    'not json' => 'logo.png',
    'no data' => json_encode(['name' => 'logo.png']),
    'not a data uri' => json_encode(['name' => 'logo.png', 'data' => BASE64_MIME_PNG]),
    'not base64' => json_encode(['name' => 'logo.png', 'data' => 'data:image/png;base64,***']),
]);
