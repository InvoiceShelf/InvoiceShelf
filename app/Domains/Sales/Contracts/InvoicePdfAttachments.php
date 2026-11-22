<?php

namespace App\Domains\Sales\Contracts;

use App\Platform\Pdf\Rendering\FacturXAttachment;

/**
 * The structured XML embedded in an invoice's PDF, which makes it a hybrid
 * e-invoice. Modules supply it; the Sales domain only asks.
 */
interface InvoicePdfAttachments
{
    /**
     * Whether anything may be attached to this company's invoice PDFs now,
     * from settings alone. An archived PDF is current only while this answer
     * is the one it was archived under.
     */
    public function applyTo(int $companyId): bool;

    /** The XML to embed, or null for the ordinary PDF. Never throws. */
    public function forInvoice(int $companyId, int $invoiceId): ?FacturXAttachment;
}
