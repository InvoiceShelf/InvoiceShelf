<?php

const OFFICIAL_MARKETPLACE_KEY_ID = 'official-modules-2026-01';
const OFFICIAL_MARKETPLACE_PUBLIC_KEY = 'sIDGuOAaMVzPv9I/GPbWp9ci5aUI5HcM5rZ0tKxW6dc=';
const OFFICIAL_MARKETPLACE_KEY_ID_2026_09 = 'official-modules-2026-09';
const OFFICIAL_MARKETPLACE_PUBLIC_KEY_2026_09 = 'VK8b5GsK7T7JFcQutq6Rv/xQ98Ata/HP1C74ZNNlYEo=';

test('marketplace configuration advertises module API 1.3 by default', function () {
    expect(marketplaceConfigFor(null)['module_api_version'])->toBe('1.3.0');
});

test('marketplace configuration includes the official signing keys by default', function () {
    expect(marketplacePublicKeysConfigFor(null))->toBe([
        OFFICIAL_MARKETPLACE_KEY_ID => OFFICIAL_MARKETPLACE_PUBLIC_KEY,
        OFFICIAL_MARKETPLACE_KEY_ID_2026_09 => OFFICIAL_MARKETPLACE_PUBLIC_KEY_2026_09,
    ]);
});

test('marketplace public-key configuration adds and rotates trusted keys', function () {
    $keys = marketplacePublicKeysConfigFor(json_encode([
        'rotated-modules-2027-01' => 'additional-public-key',
        OFFICIAL_MARKETPLACE_KEY_ID => 'replacement-public-key',
    ], JSON_THROW_ON_ERROR));

    expect($keys)->toBe([
        OFFICIAL_MARKETPLACE_KEY_ID => 'replacement-public-key',
        OFFICIAL_MARKETPLACE_KEY_ID_2026_09 => OFFICIAL_MARKETPLACE_PUBLIC_KEY_2026_09,
        'rotated-modules-2027-01' => 'additional-public-key',
    ]);
});

function marketplacePublicKeysConfigFor(?string $override): array
{
    return marketplaceConfigFor($override)['public_keys'];
}

function marketplaceConfigFor(?string $override): array
{
    $previous = getenv('MARKETPLACE_PUBLIC_KEYS');

    if ($override === null) {
        putenv('MARKETPLACE_PUBLIC_KEYS');
        unset($_ENV['MARKETPLACE_PUBLIC_KEYS'], $_SERVER['MARKETPLACE_PUBLIC_KEYS']);
    } else {
        putenv("MARKETPLACE_PUBLIC_KEYS={$override}");
        $_ENV['MARKETPLACE_PUBLIC_KEYS'] = $override;
        $_SERVER['MARKETPLACE_PUBLIC_KEYS'] = $override;
    }

    $configuration = require config_path('invoiceshelf.php');

    if ($previous === false) {
        putenv('MARKETPLACE_PUBLIC_KEYS');
        unset($_ENV['MARKETPLACE_PUBLIC_KEYS'], $_SERVER['MARKETPLACE_PUBLIC_KEYS']);
    } else {
        putenv("MARKETPLACE_PUBLIC_KEYS={$previous}");
        $_ENV['MARKETPLACE_PUBLIC_KEYS'] = $previous;
        $_SERVER['MARKETPLACE_PUBLIC_KEYS'] = $previous;
    }

    return $configuration['marketplace'];
}
