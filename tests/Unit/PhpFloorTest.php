<?php

test('the installer asks for at least the PHP the dependencies run on', function () {
    // The updater registers each release with this value and the installer
    // checks it, so it must never be lower than what Composer's platform
    // check refuses to boot below.
    $check = (string) file_get_contents(base_path('vendor/composer/platform_check.php'));

    expect(preg_match('/PHP_VERSION_ID >= (\d+)/', $check, $match))->toBe(1);

    $id = (int) $match[1];
    $floor = intdiv($id, 10000).'.'.intdiv($id % 10000, 100).'.'.($id % 100);

    expect(version_compare(config('installer.core.minPhpVersion'), $floor, '>='))->toBeTrue();
});
