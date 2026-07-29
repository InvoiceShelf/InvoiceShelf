<?php

namespace App\Support\Pdf;

use App\Support\Net\BlockedUrlException;
use App\Support\Net\PrivateNetworkGuard;
use Gotenberg\Gotenberg;
use Gotenberg\Stream;
use Psr\Http\Message\RequestInterface;

class GotenbergPdfDriver implements PdfDriver
{
    public function loadView(string $template): ResponseStream
    {
        return new GotenbergPdfResponse(Gotenberg::send($this->buildRequest($template)));
    }

    /**
     * Assemble the Chromium request without sending it.
     *
     * Split out so the option wiring can actually be asserted on. Everything
     * below this line used to be inlined into loadView(), which meant the only
     * way to check that an option was set was to run a Gotenberg service.
     */
    public function buildRequest(string $template): RequestInterface
    {
        $papersize = explode(' ', config('pdf.connections.gotenberg.papersize'));
        if (count($papersize) != 2) {
            throw new \InvalidArgumentException('Invalid Gotenberg Papersize specified');
        }

        $host = config('pdf.connections.gotenberg.host');

        // SSRF guard: gotenberg_host is an admin-supplied URL the server POSTs
        // the rendered HTML to, and whose response is streamed back as the PDF.
        // Block private/reserved/link-local targets even if set via env/seed/stale
        // config or reachable through DNS rebinding. The single exception is the
        // host the operator declared in GOTENBERG_ALLOWED_PRIVATE_HOST, which is
        // how a sidecar deployment is supported — see GotenbergHostPolicy.
        if (! GotenbergHostPolicy::isExemptFromPrivateNetworkGuard((string) $host)) {
            try {
                PrivateNetworkGuard::assertAllowed((string) $host);
            } catch (BlockedUrlException $e) {
                throw new \InvalidArgumentException('Invalid Gotenberg host: '.$e->getMessage());
            }
        }

        return Gotenberg::chromium($host)
            ->pdf()
            // Only affects the root (body/html) background: Chromium paints
            // element backgrounds either way, verified against gotenberg:8, so
            // no stock template changes. dompdf does paint the body background,
            // so this is here to stop a custom template that sets one from
            // rendering differently depending on the selected driver.
            ->printBackground()
            // config/dompdf.php renders as `screen`; Chromium defaults to `print`.
            // Align them so a template with media queries behaves the same either
            // way rather than depending on which driver is selected.
            ->emulateScreenMediaType()
            ->margins(0, 0, 0, 0)
            ->paperSize($papersize[0], $papersize[1])
            ->html(
                // The SDK renames this to index.html regardless of what we pass
                // (ChromiumPdf::html()), so name it that way rather than implying
                // a choice we do not have.
                Stream::string(
                    'index.html',
                    view($template)->render(),
                )
            );
    }
}
