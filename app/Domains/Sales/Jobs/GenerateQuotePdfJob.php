<?php

namespace App\Domains\Sales\Jobs;

use App\Domains\Sales\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Renders a quote's PDF off the request thread.
 *
 * The stored file is named after the document number, so anything that can
 * change that number — an edit, a re-render after a conversion — asks for the
 * previous file to be dropped first rather than leaving a stale twin behind.
 */
class GenerateQuotePdfJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $quote;

    public $deleteExistingFile;

    /**
     * @param  Quote  $quote
     * @param  bool  $deleteExistingFile  drop the previously stored file first
     */
    public function __construct(
        $quote,
        $deleteExistingFile = false
    ) {
        $this->quote = $quote;
        $this->deleteExistingFile = $deleteExistingFile;
    }

    /**
     * Hands the work to the document itself and always reports success — the
     * return value is a leftover of the queue contract; nothing reads it.
     */
    public function handle(): int
    {
        $document = $this->quote;

        $document->generatePDF('quote', $document->quote_number, $this->deleteExistingFile);

        return 0;
    }
}
