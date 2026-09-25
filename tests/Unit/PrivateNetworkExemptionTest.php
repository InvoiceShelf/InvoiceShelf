<?php

use App\Support\Net\PrivateNetworkGuard;

/*
 * Private network exemptions are named per feature in config/network.php. A URL
 * entry exempts exactly that URL; a bare entry exempts that host.
 */

test('a url entry exempts exactly that url', function () {
    config(['network.allowed_private_hosts.gotenberg' => ['http://pdf:3000']]);

    expect(PrivateNetworkGuard::isExempt('gotenberg', 'HTTP://PDF:3000/'))->toBeTrue()
        ->and(PrivateNetworkGuard::isExempt('gotenberg', 'http://pdf:3001'))->toBeFalse()
        ->and(PrivateNetworkGuard::isExempt('gotenberg', 'https://pdf:3000'))->toBeFalse()
        ->and(PrivateNetworkGuard::isExempt('gotenberg', 'http://pdf:3000/render'))->toBeFalse();
});

test('a host entry exempts that host, bare or inside a url', function () {
    config(['network.allowed_private_hosts.mail' => ['mail.lan', '192.168.1.10', '::1']]);

    expect(PrivateNetworkGuard::isExempt('mail', 'MAIL.lan'))->toBeTrue()
        ->and(PrivateNetworkGuard::isExempt('mail', 'smtp://user:pass@192.168.1.10:25'))->toBeTrue()
        ->and(PrivateNetworkGuard::isExempt('mail', '[::1]'))->toBeTrue()
        ->and(PrivateNetworkGuard::isExempt('mail', '192.168.1.11'))->toBeFalse();
});

test('an exemption belongs to one feature only', function () {
    config([
        'network.allowed_private_hosts.mail' => ['192.168.1.10'],
        'network.allowed_private_hosts.gotenberg' => [],
    ]);

    expect(PrivateNetworkGuard::isExempt('mail', '192.168.1.10'))->toBeTrue()
        ->and(PrivateNetworkGuard::isExempt('gotenberg', 'http://192.168.1.10'))->toBeFalse()
        ->and(PrivateNetworkGuard::isExempt('storage', '192.168.1.10'))->toBeFalse();
});

test('nothing is exempt by default', function (string $feature, string $target) {
    expect(PrivateNetworkGuard::isExempt($feature, $target))->toBeFalse();
})->with([
    ['gotenberg', 'http://pdf:3000'],
    ['mail', 'mail.lan'],
    ['mail', ''],
]);

test('the exemptions are read from the environment', function () {
    putenv('GOTENBERG_ALLOWED_PRIVATE_HOST=http://pdf:3000');
    putenv('MAIL_ALLOWED_PRIVATE_HOSTS= mail.lan , [fd00::25] ,');

    try {
        $config = require config_path('network.php');
    } finally {
        putenv('GOTENBERG_ALLOWED_PRIVATE_HOST');
        putenv('MAIL_ALLOWED_PRIVATE_HOSTS');
    }

    expect($config['allowed_private_hosts']['gotenberg'])->toBe(['http://pdf:3000'])
        ->and($config['allowed_private_hosts']['mail'])->toBe(['mail.lan', 'fd00::25']);
});
