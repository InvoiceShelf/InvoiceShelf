<?php

use App\Services\ExtensionCatalog;

test('extension catalog normalizes a single object root', function () {
    $catalog = ExtensionCatalog::make();

    $rows = $catalog->normalizePayload([
        'name' => 'Single',
        'description' => 'D',
        'version' => '1.0.0',
        'repository' => 'https://github.com/acme/cool-ext',
        'download_url' => 'https://example.com/z.zip',
    ]);

    expect($rows)->toHaveCount(1);
    expect($rows[0]['slug'])->toBe('cool-ext');
    expect($rows[0]['module_name'])->toBe('CoolExt');
});
