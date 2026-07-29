<?php

namespace App\Support\Pdf;

use Barryvdh\DomPDF\PDF;
use Illuminate\Http\Response;

/**
 * Adapts the dompdf wrapper to {@see ResponseStream}.
 *
 * The wrapper already offers stream/download/output with matching semantics,
 * so this only exists to hold it to the same contract Gotenberg is held to.
 * Without it the factory hands back a vendor object that happens to look right,
 * which is how `download()` came to be missing on the Gotenberg side without
 * anything noticing.
 */
class DompdfResponse implements ResponseStream
{
    public function __construct(protected PDF $pdf) {}

    public function stream(string $filename = 'document.pdf'): Response
    {
        return $this->pdf->stream($filename);
    }

    public function download(string $filename = 'document.pdf'): Response
    {
        return $this->pdf->download($filename);
    }

    public function output(): string
    {
        return $this->pdf->output();
    }
}
