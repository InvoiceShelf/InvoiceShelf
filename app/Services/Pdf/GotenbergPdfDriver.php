<?php

namespace App\Services\Pdf;

use Gotenberg\Gotenberg;
use Gotenberg\Stream;

class GotenbergPdfDriver
{
    public function loadView(string $viewname): GotenbergPdfResponse
    {
        $papersize = explode(' ', config('pdf.connections.gotenberg.papersize'));
        if (count($papersize) != 2) {
            throw new \InvalidArgumentException('Invalid Gotenberg Papersize specified');
        }

        $host = config('pdf.connections.gotenberg.host');
        $request = Gotenberg::chromium($host)
            ->pdf()
            ->margins(0, 0, 0, 0)
            ->paperSize($papersize[0], $papersize[1])
            ->html(
                Stream::string(
                    'document.html',
                    view($viewname)->render(),
                )
            );
        $result = Gotenberg::send($request);

        return new GotenbergPdfResponse($result);
    }
}
