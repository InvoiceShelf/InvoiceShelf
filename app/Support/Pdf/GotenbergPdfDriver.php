<?php

namespace App\Support\Pdf;

use App\Support\Net\BlockedUrlException;
use App\Support\Net\PrivateNetworkGuard;
use Gotenberg\Gotenberg;
use Gotenberg\Stream;
use Illuminate\Support\Facades\View;

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

        $headerView = $viewname.'_header';
        $footerView = $viewname.'_footer';
        $hasHeader = View::exists($headerView);
        $hasFooter = View::exists($footerView);

        $marginTop = $hasHeader ? config('pdf.connections.gotenberg.header_margin', '25mm') : 0;
        $marginBottom = $hasFooter ? config('pdf.connections.gotenberg.footer_margin', '20mm') : 0;

        $request = Gotenberg::chromium($host)
            ->pdf()
            ->margins($marginTop, $marginBottom, 0, 0)
            ->paperSize($papersize[0], $papersize[1])
            ->html(
                Stream::string(
                    'index.html',
                    view($viewname)->render(),
                )
            );

        if ($hasHeader) {
            $request->header(Stream::string('header.html', view($headerView)->render()));
        }

        if ($hasFooter) {
            $request->footer(Stream::string('footer.html', view($footerView)->render()));
        }

        $result = Gotenberg::send($request);

        return new GotenbergPdfResponse($result);
    }
}
