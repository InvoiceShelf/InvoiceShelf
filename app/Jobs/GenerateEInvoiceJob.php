<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\EInvoice\EInvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateEInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Invoice $invoice;

    public string $format;

    public bool $saveToStorage;

    /**
     * Create a new job instance.
     */
    public function __construct(Invoice $invoice, string $format = 'UBL', bool $saveToStorage = true)
    {
        $this->invoice = $invoice;
        $this->format = $format;
        $this->saveToStorage = $saveToStorage;
    }

    /**
     * Execute the job.
     */
    public function handle(EInvoiceService $eInvoiceService): void
    {
        try {
            Log::info("Generating e-invoice for invoice {$this->invoice->id} in format {$this->format}");

            $result = $eInvoiceService->generate($this->invoice, $this->format, $this->saveToStorage);

            Log::info("E-invoice generated successfully for invoice {$this->invoice->id}", [
                'format' => $this->format,
                'files' => $result['saved_files'] ?? [],
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to generate e-invoice for invoice {$this->invoice->id}", [
                'format' => $this->format,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("E-invoice generation job failed for invoice {$this->invoice->id}", [
            'format' => $this->format,
            'error' => $exception->getMessage(),
        ]);
    }
}
