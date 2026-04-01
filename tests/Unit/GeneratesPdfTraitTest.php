<?php

use App\Traits\GeneratesPdfTrait;
use Symfony\Component\HttpFoundation\StreamedResponse;

test('generated pdfs stream remote files through the application response', function () {
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, 'remote-pdf-content');
    rewind($stream);

    $pdfSource = new class($stream)
    {
        use GeneratesPdfTrait;

        public int $company_id = 1;

        public string $invoice_number = 'INV-0001';

        public function __construct(public $stream) {}

        public function getGeneratedPDF($collection_name)
        {
            return collect([
                'stream' => $this->stream,
                'file_name' => 'INV-0001.pdf',
            ]);
        }
    };

    $response = $pdfSource->getGeneratedPDFOrStream('invoice');

    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->headers->get('content-type'))->toBe('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('INV-0001.pdf');

    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    expect($content)->toBe('remote-pdf-content');
});
