<?php

namespace App\Adapters\Sales;

use App\Domains\Sales\Contracts\InvoicePdfAttachments;
use App\Platform\Pdf\Rendering\FacturXAttachment;
use Illuminate\Contracts\Container\Container;
use InvoiceShelf\Modules\Contracts\Host\PdfCapabilities;
use InvoiceShelf\Modules\Pdf\Contracts\InvoicePdfAttachmentProvider;
use InvoiceShelf\Modules\Registry;
use Throwable;

/**
 * Asks the enabled modules' invoice PDF attachment providers, in slug order.
 * The registry only holds providers of enabled modules.
 *
 * A provider that throws is reported and skipped, so a broken module can
 * never stop an invoice from being rendered. Providers are asked only when
 * the PDF service can embed the XML at all.
 */
class ModuleInvoicePdfAttachments implements InvoicePdfAttachments
{
    public function __construct(
        private readonly Container $container,
        private readonly PdfCapabilities $capabilities,
    ) {}

    public function applyTo(int $companyId): bool
    {
        foreach ($this->providers() as $provider) {
            try {
                if ($provider->appliesTo($companyId)) {
                    return true;
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return false;
    }

    public function forInvoice(int $companyId, int $invoiceId): ?FacturXAttachment
    {
        $providers = $this->providers();

        if ($providers === [] || ! $this->capabilities->embedsFacturX()) {
            return null;
        }

        foreach ($providers as $provider) {
            try {
                if (! $provider->appliesTo($companyId)) {
                    continue;
                }

                $attachment = $provider->attachmentFor($companyId, $invoiceId);

                if ($attachment !== null) {
                    return new FacturXAttachment($attachment->xml, $attachment->profile);
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return null;
    }

    /** @return list<InvoicePdfAttachmentProvider> */
    private function providers(): array
    {
        $registrations = Registry::allDrivers(Registry::INVOICE_PDF_ATTACHMENT);
        ksort($registrations);

        $providers = [];

        foreach ($registrations as $registration) {
            try {
                $provider = $this->container->make($registration['class']);
            } catch (Throwable $exception) {
                report($exception);

                continue;
            }

            if ($provider instanceof InvoicePdfAttachmentProvider) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }
}
