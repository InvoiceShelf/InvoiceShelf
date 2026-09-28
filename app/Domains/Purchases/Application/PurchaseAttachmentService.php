<?php

namespace App\Domains\Purchases\Application;

use App\Domains\Purchases\Models\Bill;
use App\Domains\Purchases\Models\SupplierCredit;
use App\Support\Media\SafeFileName;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PurchaseAttachmentService
{
    public function attach(Bill|SupplierCredit $document, UploadedFile $file, ?int $actorId): Media
    {
        PurchaseInputs::ensure($document->status !== 'VOID', 'file', 'A void document cannot accept attachments.');
        $media = $document->addMedia($file)->usingFileName(SafeFileName::from($file->getClientOriginalName()))->toMediaCollection('purchase_documents');

        return $media;
    }
}
