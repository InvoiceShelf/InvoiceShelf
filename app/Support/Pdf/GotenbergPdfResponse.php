<?php

namespace App\Support\Pdf;

use Illuminate\Http\Response;
use Psr\Http\Message\ResponseInterface;

class GotenbergPdfResponse implements ResponseStream
{
    protected ResponseInterface $response;

    public function __construct($stream)
    {
        $this->response = $stream;
    }

    public function stream(string $filename = 'document.pdf'): Response
    {
        return $this->respond($filename, 'inline');
    }

    public function download(string $filename = 'document.pdf'): Response
    {
        return $this->respond($filename, 'attachment');
    }

    public function output(): string
    {
        $body = $this->response->getBody();

        // getContents() reads from wherever the stream currently sits, so a
        // second call would hand back nothing. Rewind so output() is repeatable
        // and safe to mix with stream()/download() on the same instance.
        if ($body->isSeekable()) {
            $body->rewind();
        }

        return $body->getContents();
    }

    private function respond(string $filename, string $disposition): Response
    {
        return new Response($this->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
        ]);
    }
}
