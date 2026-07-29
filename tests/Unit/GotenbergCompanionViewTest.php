<?php

use App\Support\Pdf\GotenbergPdfDriver;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

/**
 * Chromium repeats a header/footer template on every page. A `{template}_header`
 * or `{template}_footer` view alongside the document opts into that, and page
 * numbers are the built-in footer for templates that supply none.
 *
 * Assertions are on the multipart request rather than a rendered PDF, so no
 * Gotenberg service is needed. The rendered check against a live gotenberg:8 is
 * in the PR description.
 *
 * Views are written to a temp namespace rather than resources/views so they stay
 * out of the template picker and out of the render test's glob.
 */
beforeEach(function () {
    config([
        'pdf.connections.gotenberg.host' => 'http://gotenberg.example.com:3000',
        'pdf.page.page_numbers' => false,
    ]);

    $this->views = sys_get_temp_dir().'/is-companion-'.uniqid();
    File::ensureDirectoryExists($this->views);
    View::addNamespace('companion', $this->views);

    $this->writeView = function (string $name, string $contents) {
        File::put($this->views."/{$name}.blade.php", $contents);
    };

    ($this->writeView)('doc', '<html><body>document body</body></html>');
});

afterEach(function () {
    File::deleteDirectory($this->views);
});

function companionRequestBody(string $view = 'companion::doc'): string
{
    return (string) (new GotenbergPdfDriver)->buildRequest($view)->getBody();
}

test('a template with no companions and no page numbers sends neither', function () {
    $body = companionRequestBody();

    expect($body)->not->toContain('header.html')
        ->and($body)->not->toContain('footer.html');
});

test('a companion header is sent alongside the document', function () {
    ($this->writeView)('doc_header', '<div>COMPANION HEADER</div>');

    $body = companionRequestBody();

    expect($body)->toContain('header.html')
        ->and($body)->toContain('COMPANION HEADER')
        ->and($body)->not->toContain('footer.html');
});

test('a companion footer is sent alongside the document', function () {
    ($this->writeView)('doc_footer', '<div>COMPANION FOOTER</div>');

    $body = companionRequestBody();

    expect($body)->toContain('footer.html')
        ->and($body)->toContain('COMPANION FOOTER');
});

test('page numbers supply a footer when the template has none', function () {
    config(['pdf.page.page_numbers' => true]);

    $body = companionRequestBody();

    expect($body)->toContain('footer.html')
        ->and($body)->toContain('pageNumber')
        ->and($body)->toContain('totalPages');
});

/**
 * A template that went to the trouble of defining a footer should keep it, so
 * enabling page numbers must not overwrite it.
 */
test('a companion footer wins over the page-number default', function () {
    config(['pdf.page.page_numbers' => true]);
    ($this->writeView)('doc_footer', '<div>COMPANION FOOTER</div>');

    $body = companionRequestBody();

    expect($body)->toContain('COMPANION FOOTER')
        ->and($body)->not->toContain('pageNumber');
});

test('page numbers stay off by default so existing documents are unchanged', function () {
    expect(config('pdf.page.page_numbers'))->toBeFalsy();
});
