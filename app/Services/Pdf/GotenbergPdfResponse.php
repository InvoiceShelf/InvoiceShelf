<?php

namespace App\Services\Pdf;

use Illuminate\Http\Response;
use Psr\Http\Message\ResponseInterface;

class GotenbergPdfResponse
{
    protected ResponseInterface $response;

    public function __construct($stream)
    {
        $this->response = $stream;
    }

    public function stream(string $filename = 'document.pdf'): Response
    {
        $output = $this->response->getBody();

        return new Response($output, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    public function output(): string
    {
        return $this->response->getBody()->getContents();
    }
}
