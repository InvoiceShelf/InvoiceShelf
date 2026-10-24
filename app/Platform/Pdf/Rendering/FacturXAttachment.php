<?php

namespace App\Platform\Pdf\Rendering;

use InvalidArgumentException;

/**
 * A Factur-X / ZUGFeRD XML to embed in a rendered PDF, which makes it a
 * hybrid e-invoice: a PDF/A-3b file that people read and software reads the
 * XML inside.
 *
 * The PDF platform is handed finished XML and its profile and knows nothing
 * about invoices; modules supply it (see the Sales domain's
 * InvoicePdfAttachments). Only Gotenberg can build the PDF/A-3 container;
 * dompdf ignores the attachment.
 */
final class FacturXAttachment
{
    /** The file name Factur-X and ZUGFeRD readers look for inside the PDF. */
    public const FILENAME = 'factur-x.xml';

    /**
     * The archival conformance a hybrid PDF is built to. A file may only be
     * embedded from PDF/A-3 on, and `b` is the level the e-invoicing
     * specifications ask for.
     */
    public const PDFA_CONFORMANCE = 'PDF/A-3b';

    public const PROFILE_EN_16931 = 'EN 16931';

    /** The conformance levels the Factur-X metadata can name, spelled as Gotenberg expects them. */
    public const PROFILES = ['MINIMUM', 'BASIC WL', 'BASIC', self::PROFILE_EN_16931, 'EXTENDED', 'XRECHNUNG'];

    public function __construct(
        public readonly string $xml,
        public readonly string $profile = self::PROFILE_EN_16931,
    ) {
        if (trim($xml) === '') {
            throw new InvalidArgumentException('A Factur-X attachment cannot carry empty XML.');
        }

        if (! in_array($profile, self::PROFILES, true)) {
            throw new InvalidArgumentException("Unknown Factur-X profile '{$profile}'.");
        }
    }
}
