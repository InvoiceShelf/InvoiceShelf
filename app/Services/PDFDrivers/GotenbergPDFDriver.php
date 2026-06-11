<?php

namespace App\Services\PDFDrivers;

use App\Rules\SafeRemoteUrl;
use Gotenberg\Gotenberg;
use Gotenberg\Stream;
use Illuminate\Http\Response;
use Psr\Http\Message\ResponseInterface;

class GotenbergPDFResponse
{
    /** @var ResponseInterface */
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
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
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
        $papersize = explode(' ', config('pdf.connections.gotenberg.papersize'));
        if (count($papersize) != 2) {
            throw new \InvalidArgumentException('Invalid Gotenberg Papersize specified');
        }

        $host = config('pdf.connections.gotenberg.host');

        // Defense in depth against SSRF: a host that bypassed request-time
        // validation (env/seed/stale config, or DNS rebinding) must still not
        // be able to target internal/private addresses.
        if (! SafeRemoteUrl::isSafe((string) $host)) {
            throw new \RuntimeException('Refusing to render PDF: unsafe Gotenberg host.');
        }

        $request = Gotenberg::chromium($host)
            ->pdf()
            ->margins(0, 0, 0, 0) // Margins can be set using CSS
            ->paperSize($papersize[0], $papersize[1])
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
