<?php

namespace App\Domains\Purchases\Http\Resources;

use App\Domains\Purchases\Models\RecurringCost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RecurringCost */
class RecurringCostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'supplier_id' => (int) $this->supplier_id,
            'name' => (string) $this->name,
            'mode' => (string) $this->mode,
            'status' => (string) $this->status,
            'frequency' => (string) $this->frequency,
            'interval' => (int) $this->interval,
            'timezone' => (string) $this->timezone,
            'starts_at' => (string) $this->starts_at,
            'next_run_at' => $this->next_run_at === null ? null : (string) $this->next_run_at,
            'ends_at' => $this->ends_at === null ? null : (string) $this->ends_at,
            'max_occurrences' => $this->max_occurrences === null ? null : (int) $this->max_occurrences,
            'occurrence_count' => (int) $this->occurrence_count,
            'due_days' => (int) $this->due_days,
            'auto_record_paid' => (bool) $this->auto_record_paid,
            'template' => (object) ($this->template ?? []),
            'last_error' => $this->last_error === null ? null : (string) $this->last_error,
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'occurrences' => $this->whenLoaded('occurrences', fn () => $this->occurrenceRows()),
            'activities' => PurchaseActivityResource::collection($this->whenLoaded('activities')),
        ];
    }

    /** @return list<array{id: int, scheduled_for: string, record_type: string, record_id: int}> */
    private function occurrenceRows(): array
    {
        return $this->occurrences->map(fn ($row) => ['id' => (int) $row->id, 'scheduled_for' => (string) $row->scheduled_for, 'record_type' => (string) $row->record_type, 'record_id' => (int) $row->record_id])->all();
    }
}
