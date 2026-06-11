<?php

namespace App\Support\Pdf;

use App\Support\Net\BlockedUrlException;
use App\Support\Net\PrivateNetworkGuard;
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

        // SSRF guard: gotenberg_host is an admin-supplied URL the server POSTs
        // the rendered HTML to. Block private/reserved/link-local targets even
        // if set via env/seed/stale config or reachable through DNS rebinding.
        try {
            PrivateNetworkGuard::assertAllowed((string) $host);
        } catch (BlockedUrlException $e) {
            throw new \InvalidArgumentException('Invalid Gotenberg host: '.$e->getMessage());
        }

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
