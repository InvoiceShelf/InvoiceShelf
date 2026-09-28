<?php

namespace App\Domains\Purchases\Http\Controllers\Company;

use App\Domains\Purchases\Application\PurchaseAttachmentService;
use App\Domains\Purchases\Http\Requests\PurchaseAttachmentRequest;
use App\Domains\Purchases\Models\Bill;
use App\Domains\Purchases\Models\SupplierCredit;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PurchaseAttachmentsController extends Controller
{
    public function store(PurchaseAttachmentRequest $request, string $kind, int $record, PurchaseAttachmentService $service)
    {
        $document = $this->document($kind, $record, (int) $request->header('company'));
        $this->authorize('update', $document);
        $file = $service->attach($document, $request->file('file'), $request->user()->id);

        return response()->json(['data' => ['id' => $file->id, 'name' => $file->file_name]], 201);
    }

    public function show(Request $request, string $kind, int $record, int $attachment)
    {
        $document = $this->document($kind, $record, (int) $request->header('company'));
        $this->authorize('view', $document);
        $file = $document->media()->where('collection_name', 'purchase_documents')->findOrFail($attachment);

        return Storage::disk($file->disk)->download($file->getPathRelativeToRoot(), $file->file_name, ['Cache-Control' => 'private, no-store']);
    }

    private function document(string $kind, int $id, int $companyId): Bill|SupplierCredit
    {
        $model = match ($kind) {
            'bills' => Bill::class, 'supplier-credits' => SupplierCredit::class, default => abort(404)
        };

        return $model::query()->forCompany($companyId)->findOrFail($id);
    }
}
