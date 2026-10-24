<?php

namespace App\Platform\Pdf\Rendering;

use App\Platform\Pdf\Application\FontService;
use App\Support\Net\BlockedUrlException;
use App\Support\Net\PrivateNetworkGuard;
use Gotenberg\FacturX;
use Gotenberg\Gotenberg;
use Gotenberg\Stream;
use Illuminate\Support\Facades\View;
use Psr\Http\Message\RequestInterface;

class GotenbergPdfDriver implements PdfDriver
{
    public function loadView(
        string $template,
        array $metadata = [],
        ?PdfPageSetup $page = null,
        ?FacturXAttachment $attachment = null,
    ): ResponseStream {
        return new GotenbergPdfResponse(
            Gotenberg::send($this->buildRequest($template, $metadata, $page, $attachment))
        );
    }

    /**
     * Assemble the Chromium request without sending it.
     *
     * Split out so the option wiring can actually be asserted on. Everything
     * below this line used to be inlined into loadView(), which meant the only
     * way to check that an option was set was to run a Gotenberg service.
     */
    public function buildRequest(
        string $template,
        array $metadata = [],
        ?PdfPageSetup $page = null,
        ?FacturXAttachment $attachment = null,
    ): RequestInterface {
        $page ??= PdfPageSetup::fromConfig();
        [$width, $height] = $page->gotenbergPaper();
        [$marginTop, $marginBottom, $marginLeft, $marginRight] = $page->gotenbergMargins();

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

        $chromium = Gotenberg::chromium($host)
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
            ->margins($marginTop, $marginBottom, $marginLeft, $marginRight)
            ->paperSize($width, $height);

        // landscape() swaps the axes itself, so paperSize() above is always given
        // the portrait pair — the same convention dompdf's setPaper() follows.
        if ($page->isLandscape()) {
            $chromium->landscape();
        }

        // Archival conformance, converted by LibreOffice inside the Gotenberg
        // image. PDF/A-3 is what the EU e-invoicing formats ask for. The value is
        // passed through unvalidated by the SDK, so an unsupported one surfaces
        // as an HTTP error from the service; the setting is a fixed list for
        // that reason.
        //
        // An embedded XML dictates the conformance rather than inheriting it: a
        // file may only be attached from PDF/A-3 on, so a hybrid e-invoice built
        // to whatever the instance happens to have configured would not be a
        // valid one. Set in one branch or the other, never both — the SDK
        // appends form fields, so calling pdfa() twice would send two values.
        $pdfa = $attachment !== null
            ? FacturXAttachment::PDFA_CONFORMANCE
            : config('pdf.connections.gotenberg.pdfa');

        if ($attachment !== null) {
            $chromium
                ->pdfa($pdfa)
                ->facturX(new FacturX(
                    Stream::string(FacturXAttachment::FILENAME, $attachment->xml),
                    $attachment->profile,
                ));
        } elseif ($pdfa) {
            $chromium->pdfa($pdfa);
        }

        if ($metadata !== []) {
            $chromium->metadata($pdfa ? self::pdfaMetadata($metadata) : $metadata);
        }

        // Must be attached before html(), which is terminal: it returns the built
        // request rather than the builder.
        if ($header = $this->companion($template, '_header')) {
            $chromium->header(Stream::string('header.html', $header));
        }

        if ($footer = $this->companion($template, '_footer') ?? $this->defaultFooter()) {
            $chromium->footer(Stream::string('footer.html', $footer));
        }

        $html = view($template)->render();

        // Fonts must travel with the document; see attachFonts().
        [$html, $fonts] = $this->attachFonts($html);

        if ($fonts !== []) {
            $chromium->assets(...$fonts);
        }

        return $chromium->html(
            // The SDK renames this to index.html regardless of what we pass
            // (ChromiumPdf::html()), so name it that way rather than implying
            // a choice we do not have.
            Stream::string('index.html', $html)
        );
    }

    /**
     * The document properties a PDF/A file may carry.
     *
     * Gotenberg writes metadata with exiftool, and exiftool stores the bare
     * Author key in XMP as pdf:Author — a property the Adobe PDF schema does
     * not define, so veraPDF rejects the file under ISO 19005-3 clause
     * 6.6.2.3.1. The archival home for the authoring entity is dc:creator,
     * which is where exiftool maps the bare Creator key, so Author is folded
     * into Creator and the application name it displaces is dropped.
     *
     * @param  array<string, string>  $metadata
     * @return array<string, string>
     */
    private static function pdfaMetadata(array $metadata): array
    {
        if (isset($metadata['Author'])) {
            $metadata['Creator'] = $metadata['Author'];
            unset($metadata['Author']);
        }

        return $metadata;
    }

    /**
     * Send the installed font files alongside the document, and point the
     * font-face rules at them.
     *
     * FontService emits `src: url("/var/www/html/storage/fonts/...")` — an
     * absolute path on this container's filesystem. dompdf shares that
     * filesystem so it resolves; Chromium runs inside the Gotenberg container
     * and cannot see any of it, so every package silently failed to load and
     * documents fell back to whatever fonts that image happens to ship. The
     * docs recommend Gotenberg precisely for mixed-script documents, which made
     * this the wrong way round.
     *
     * Gotenberg unpacks assets next to index.html, so a bare filename resolves.
     * Only fonts actually referenced by the markup are sent, to keep a CJK
     * package off every request that does not use it.
     *
     * @return array{0: string, 1: list<Stream>}
     */
    private function attachFonts(string $html): array
    {
        $streams = [];

        foreach (app(FontService::class)->getInstalledFontFilePaths() as $filename => $path) {
            if (! str_contains($html, $path) || ! is_readable($path)) {
                continue;
            }

            $html = str_replace($path, $filename, $html);
            $streams[] = Stream::path($path, $filename);
        }

        return [$html, $streams];
    }

    /**
     * A `{template}_header` or `{template}_footer` view rendered alongside the
     * document, repeated by Chromium on every page.
     *
     * The suffix resolves through the `pdf_templates::` namespace too, so a
     * custom template gets this without any extra wiring. PdfTemplateUtils hides
     * the suffixed views from the template picker, which would otherwise list
     * them as separately selectable templates.
     */
    private function companion(string $template, string $suffix): ?string
    {
        $view = $template.$suffix;

        return View::exists($view) ? View::make($view)->render() : null;
    }

    /**
     * Page numbers, when no template supplies a footer of its own.
     *
     * Gotenberg is the only driver that can do this: Chromium repeats a footer
     * template on every page and substitutes the pageNumber/totalPages spans.
     * dompdf has no equivalent, so the setting is presented under Gotenberg.
     *
     * Note the footer draws inside the bottom margin, so it is invisible when
     * that margin is zero. The default of 1.2cm leaves room.
     */
    private function defaultFooter(): ?string
    {
        if (! config('pdf.page.page_numbers')) {
            return null;
        }

        return View::make('app.pdf.partials.page-footer')->render();
    }
}
