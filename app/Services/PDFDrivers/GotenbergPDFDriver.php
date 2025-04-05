<?php

namespace App\Services\PDFDrivers;

use Gotenberg\Gotenberg;
use Gotenberg\Stream;
use Illuminate\Http\Response;

class GotenbergPDFResponse
{
    /** @var \Psr\Http\Message\ResponseInterface */
    protected $response;

    public function __construct($stream)
    {
        $this->response = $stream;
    }

    public function stream(string $filename = 'document.pdf'): Response
    {
        $output = $this->response->getBody();
        return new Response($output, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' =>  'inline; filename="' . $filename . '"',
        ]);
    }

    public function output(): string
    {
        return $this->response->getBody()->getContents();
    }
}

class GotenbergPDFDriver
{
    public function loadView(string $viewname): GotenbergPDFResponse
    {
        $host = config('pdf.gotenberg.host');
        $request = Gotenberg::chromium($host)
            ->pdf()
            ->margins(0, 0, 0, 0)
            ->paperSize(8.27, 11.7)
            ->html(
                Stream::string(
                    'document.html',
                    view($viewname)->render(),
                )
            );
        $result = Gotenberg::send($request);

        return new GotenbergPDFResponse($result);
    }
}
