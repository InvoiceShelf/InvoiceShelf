<?php

use App\Support\Pdf\GotenbergPdfDriver;
use Illuminate\Support\Facades\View;

beforeEach(function () {
    config([
        'pdf.connections.gotenberg.host' => 'http://pdf.example.com:3000',
        'pdf.connections.gotenberg.papersize' => '210mm 297mm',
        'pdf.connections.gotenberg.header_margin' => '25mm',
        'pdf.connections.gotenberg.footer_margin' => '20mm',
    ]);
});

it('throws when the papersize config has an unexpected format', function () {
    config(['pdf.connections.gotenberg.papersize' => 'invalid']);

    expect(fn () => (new GotenbergPdfDriver)->loadView('app.pdf.invoice.invoice1'))
        ->toThrow(\InvalidArgumentException::class, 'Invalid Gotenberg Papersize specified');
});

it('throws when the configured gotenberg host targets a private network address', function () {
    config(['pdf.connections.gotenberg.host' => 'http://10.0.0.1:3000']);

    expect(fn () => (new GotenbergPdfDriver)->loadView('app.pdf.invoice.invoice1'))
        ->toThrow(\InvalidArgumentException::class, 'Invalid Gotenberg host');
});

it('checks for companion _header and _footer views alongside the main template', function () {
    $fakeView = new class
    {
        public function render(): string
        {
            return '<html><body>content</body></html>';
        }
    };

    View::shouldReceive('exists')->with('app.pdf.invoice.invoice1_header')->andReturn(false)->once();
    View::shouldReceive('exists')->with('app.pdf.invoice.invoice1_footer')->andReturn(false)->once();
    View::shouldReceive('make')->andReturn($fakeView);

    try {
        (new GotenbergPdfDriver)->loadView('app.pdf.invoice.invoice1');
    } catch (\Throwable) {
        // Gotenberg::send() fails without a running service — expected in unit tests.
    }

    // Mockery verifies the shouldReceive expectations on teardown.
});

it('renders companion header and footer views when they exist alongside the template', function () {
    $fakeView = new class
    {
        public function render(): string
        {
            return '<html><body>content</body></html>';
        }
    };

    View::shouldReceive('exists')->with('invoice.template_header')->andReturn(true)->once();
    View::shouldReceive('exists')->with('invoice.template_footer')->andReturn(true)->once();
    View::shouldReceive('make')->andReturn($fakeView)->times(3);

    try {
        (new GotenbergPdfDriver)->loadView('invoice.template');
    } catch (\Throwable) {
        // Gotenberg::send() fails without a running service — expected in unit tests.
    }

    // If all three make() calls (main + header + footer) happened, Mockery confirms it.
});
