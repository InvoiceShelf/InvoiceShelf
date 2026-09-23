<?php

test('installer uses the application minimum php version', function () {
    expect(config('installer.core.minPhpVersion'))
        ->toBe(config('invoiceshelf.min_php_version'))
        ->toBe('8.4.1');
});
