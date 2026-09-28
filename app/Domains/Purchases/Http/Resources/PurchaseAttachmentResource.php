<?php

namespace App\Domains\Purchases\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** @mixin Media */
class PurchaseAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $kind = $this->model_type === 'bill' ? 'bills' : 'supplier-credits';

        return [
            'id' => (int) $this->id,
            'name' => (string) $this->file_name,
            'size' => (int) $this->size,
            'url' => '/api/v1/'.$kind.'/'.$this->model_id.'/attachments/'.$this->id,
        ];
    }
}
