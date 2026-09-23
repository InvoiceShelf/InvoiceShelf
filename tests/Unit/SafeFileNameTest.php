<?php

use App\Support\SafeFileName;

test('an upload keeps only its last extension, and a name without dots', function (string $sent, string $stored) {
    expect(SafeFileName::from($sent))->toBe($stored);
})->with([
    ['logo.png', 'logo.png'],
    ['Company Logo.PNG', 'company-logo.png'],
    ['shell.php.jpg', 'shell-php.jpg'],
    ['../../etc/passwd.png', 'passwd.png'],
    ['.png', 'file.png'],
    ['no-extension', 'no-extension'],
]);
