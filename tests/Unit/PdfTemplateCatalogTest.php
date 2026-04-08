<?php

use App\Services\PdfTemplateCatalog;

it('normalizes invoice template rows', function () {
    $catalog = new PdfTemplateCatalog('https://example.test/templates.json');

    $result = $catalog->normalizePayload([
        'templates' => [
            [
                'slug' => 'modern-dark',
                'type' => 'invoice',
                'template_name' => 'ModernDark',
                'name' => 'Modern Dark',
                'version' => '1.0.0',
                'download_url' => 'https://example.org/t.zip',
                'cover' => 'https://example.org/preview.png',
            ],
        ],
    ]);

    expect($result)->toHaveCount(1)
        ->and($result[0]['catalog_kind'])->toBe('pdf_template')
        ->and($result[0]['pdf_template_type'])->toBe('invoice')
        ->and($result[0]['template_name'])->toBe('ModernDark')
        ->and($result[0]['slug'])->toBe('modern-dark')
        ->and($result[0]['module_name'])->toBe('PdfTemplate_ModernDark');
});

it('normalizes estimate template rows', function () {
    $catalog = new PdfTemplateCatalog('https://example.test/templates.json');

    $result = $catalog->normalizePayload([
        'templates' => [
            [
                'type' => 'estimate',
                'template_name' => 'Classic',
                'name' => 'Classic',
                'cover' => 'https://example.org/c.png',
            ],
        ],
    ]);

    expect($result)->toHaveCount(1)
        ->and($result[0]['pdf_template_type'])->toBe('estimate');
});

it('skips rows without cover', function () {
    $catalog = new PdfTemplateCatalog('https://example.test/templates.json');

    $result = $catalog->normalizePayload([
        'templates' => [
            [
                'type' => 'invoice',
                'template_name' => 'NoCover',
                'name' => 'No Cover',
                'cover' => '',
            ],
            [
                'type' => 'invoice',
                'template_name' => 'HasCover',
                'name' => 'Has Cover',
                'cover' => 'https://example.org/x.png',
            ],
        ],
    ]);

    expect($result)->toHaveCount(1)
        ->and($result[0]['template_name'])->toBe('HasCover');
});

it('skips invalid rows and keeps valid ones', function () {
    $catalog = new PdfTemplateCatalog('https://example.test/templates.json');

    $result = $catalog->normalizePayload([
        'templates' => [
            [
                'type' => 'invoice',
                'template_name' => '',
                'name' => 'Bad',
            ],
            [
                'type' => 'invoice',
                'template_name' => 'Good',
                'name' => 'Good',
                'cover' => 'https://example.org/g.png',
            ],
        ],
    ]);

    expect($result)->toHaveCount(1)
        ->and($result[0]['template_name'])->toBe('Good');
});
