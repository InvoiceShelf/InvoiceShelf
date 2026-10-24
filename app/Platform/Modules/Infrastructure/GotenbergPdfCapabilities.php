<?php

namespace App\Platform\Modules\Infrastructure;

use App\Platform\Pdf\Rendering\GotenbergHostPolicy;
use App\Support\Net\BlockedUrlException;
use App\Support\Net\PrivateNetworkGuard;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvoiceShelf\Modules\Contracts\Host\PdfCapabilities;
use Throwable;

/**
 * Whether invoice PDFs can carry an embedded Factur-X XML.
 *
 * Only Gotenberg can build the PDF/A-3 container, and only from 8.34, the
 * first release that reads the Factur-X fields (an older one drops them and
 * returns a plain PDF). The conversion also needs LibreOffice, which the
 * `-chromium` image lacks. So the service is asked: its `/version`, and
 * whether its `/health` reports LibreOffice. The answer is cached per host:
 * ten minutes when it is known, one minute while the service is unreachable.
 */
class GotenbergPdfCapabilities implements PdfCapabilities
{
    public const MINIMUM_VERSION = '8.34.0';

    private const CACHE_PREFIX = 'pdf.capabilities.facturx.';

    public function embedsFacturX(): bool
    {
        $host = rtrim((string) config('pdf.connections.gotenberg.host'), '/');

        if (config('pdf.driver') !== 'gotenberg' || $host === '') {
            return false;
        }

        $key = self::CACHE_PREFIX.sha1($host);
        $cached = Cache::get($key);

        if (is_bool($cached)) {
            return $cached;
        }

        $answer = $this->probe($host);
        Cache::put($key, $answer ?? false, now()->addMinutes($answer === null ? 1 : 10));

        return $answer ?? false;
    }

    /** True or false when the service answered, null when it could not be reached. */
    private function probe(string $host): ?bool
    {
        if (! GotenbergHostPolicy::isExemptFromPrivateNetworkGuard($host)) {
            try {
                PrivateNetworkGuard::assertAllowed($host);
            } catch (BlockedUrlException) {
                return false;
            }
        }

        try {
            $version = Http::timeout(3)->get($host.'/version');
            $health = Http::timeout(3)->get($host.'/health');
        } catch (Throwable) {
            return null;
        }

        if (! $version->successful() || ! $health->successful()) {
            return $version->status() === 404 ? false : null;
        }

        $number = ltrim(trim($version->body()), 'v');

        return preg_match('/^\d+\.\d+(\.\d+)?$/', $number) === 1
            && version_compare($number, self::MINIMUM_VERSION, '>=')
            && is_array($health->json('details.libreoffice'));
    }
}
