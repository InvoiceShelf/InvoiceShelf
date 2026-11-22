<?php

namespace App\Platform\Pdf\Rendering;

interface PdfDriver
{
    /**
     * @param  array<string, string>  $metadata  Document properties written into
     *                                           the file: Title, Author, Subject,
     *                                           Keywords, Creator. Both drivers
     *                                           accept the same key names.
     * @param  PdfPageSetup|null  $page  Page geometry to render at. Defaults to
     *                                   the configured one; the reports pass
     *                                   PdfPageSetup::forReports() because they
     *                                   carry no inset of their own.
     * @param  FacturXAttachment|null  $attachment  XML to embed, which makes the
     *                                              result a hybrid e-invoice
     *                                              (PDF/A-3b). Null is the
     *                                              ordinary PDF. Only a driver
     *                                              that builds a PDF/A-3
     *                                              container honours it.
     */
    public function loadView(
        string $template,
        array $metadata = [],
        ?PdfPageSetup $page = null,
        ?FacturXAttachment $attachment = null,
    ): ResponseStream;
}
