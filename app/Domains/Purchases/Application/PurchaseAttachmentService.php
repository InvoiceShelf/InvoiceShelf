<?php

namespace App\Domains\Purchases\Application;

use App\Domains\Purchases\Models\Bill;
use App\Domains\Purchases\Models\SupplierCredit;
use App\Support\Media\SafeFileName;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PurchaseAttachmentService
{
    /**
     * Attach an uploaded file to a bill or supplier credit, under a safe file
     * name. A void document takes no new attachments.
     */
    public function attach(Bill|SupplierCredit $document, UploadedFile $file, ?int $actorId): Media
    {
        PurchaseInputs::ensure($document->status !== 'VOID', 'file', 'purchase_void_document_attachment');

        $media = $document->addMedia($file)
            ->usingFileName(SafeFileName::from($file->getClientOriginalName()))
            ->toMediaCollection('purchase_documents');

        return $media;
    }
}
