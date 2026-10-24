<?php

use Symfony\Component\Yaml\Yaml;

/**
 * A hybrid e-invoice needs Gotenberg 8.34 or newer, which reads the Factur-X
 * fields (older images drop them and return a plain PDF), and the full image,
 * whose LibreOffice does the PDF/A conversion (the -chromium image refuses it).
 * Every Gotenberg this repository starts is pinned to such an image.
 */
test('every gotenberg image the repository starts can build a hybrid e-invoice', function () {
    $images = [];

    foreach (glob(base_path('docker/development/docker-compose.*.gotenberg.yml')) ?: [] as $file) {
        foreach (Yaml::parseFile($file)['services'] ?? [] as $service) {
            if (str_starts_with((string) ($service['image'] ?? ''), 'gotenberg/')) {
                $images[basename($file)] = $service['image'];
            }
        }
    }

    $workflow = Yaml::parseFile(base_path('.github/workflows/pdf-conformance.yaml'));
    $images['pdf-conformance.yaml'] = $workflow['jobs']['conformance']['services']['gotenberg']['image'] ?? null;

    expect($images)->toHaveCount(4);

    foreach ($images as $file => $image) {
        expect((string) $image)->toMatch('/^gotenberg\/gotenberg:\d+\.\d+\.\d+$/', "{$file} must pin an exact full Gotenberg image, not {$image}");

        $version = explode(':', (string) $image)[1];
        expect(version_compare($version, '8.34.0', '>='))->toBeTrue("{$file} pins {$image}, which predates Factur-X support");
    }
});
