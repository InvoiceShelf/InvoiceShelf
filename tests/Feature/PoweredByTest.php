<?php

use App\Support\PoweredBy;
use Illuminate\Support\Facades\File;

use function Pest\Laravel\getJson;

/**
 * The "Powered by" line under sign-in pages, public documents and emails, and
 * the link to the running version's source code (AGPL section 13). A host may
 * rename or hide the first; the second is always offered.
 */
test('mail says powered by InvoiceShelf unless the host says otherwise', function () {
    expect(view('emails.partials.powered-by')->render())
        ->toContain('Powered by')
        ->toContain('href="https://invoiceshelf.com"')
        ->toContain('>InvoiceShelf</a>');

    config([
        'invoiceshelf.powered_by.name' => 'Acme Hosting',
        'invoiceshelf.powered_by.url' => 'https://acme.example',
    ]);

    expect(view('emails.partials.powered-by')->render())
        ->toContain('href="https://acme.example"')
        ->toContain('>Acme Hosting</a>');
});

test('a white-label install sends mail without the line', function () {
    config(['invoiceshelf.powered_by.enabled' => false]);

    expect(trim(view('emails.partials.powered-by')->render()))->toBe('');
});

test('the thin client learns the line and the source link from the manifest', function () {
    $manifest = getJson('/api/v1/app/client-manifest')->assertOk();

    expect($manifest->json('branding.powered_by'))->toBe(['name' => 'InvoiceShelf', 'url' => 'https://invoiceshelf.com'])
        ->and($manifest->json('source_url'))->toBe(PoweredBy::sourceUrl());

    config(['invoiceshelf.powered_by.enabled' => false]);

    expect(getJson('/api/v1/app/client-manifest')->json('branding.powered_by'))->toBeNull();
});

test('the source link points at the version that is running', function () {
    $version = trim(File::get(base_path('version.md')));

    expect(PoweredBy::sourceUrl())->toBe("https://github.com/InvoiceShelf/InvoiceShelf/tree/{$version}");

    config(['invoiceshelf.source_url' => 'https://git.example/fork/archive/{version}.tar.gz']);

    expect(PoweredBy::sourceUrl())->toBe("https://git.example/fork/archive/{$version}.tar.gz");
});
