<?php

namespace App\Domains\Purchases\Http\Resources;

use App\Domains\Purchases\Models\Bill;
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
            'starts_at' => substr((string) $this->starts_at, 0, 10),
            'next_run_at' => $this->next_run_at === null ? null : (string) $this->next_run_at,
            'limit_by' => (string) $this->limit_by,
            'limit_count' => $this->limit_count === null ? null : (int) $this->limit_count,
            'limit_date' => $this->limit_date === null ? null : substr((string) $this->limit_date, 0, 10),
            'due_days' => (int) $this->due_days,
            'create_as_draft' => (bool) $this->create_as_draft,
            'notify_creator' => (bool) $this->notify_creator,
            'template' => (object) ($this->template ?? []),
            'last_error' => $this->last_error === null ? null : (string) $this->last_error,
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'occurrences' => $this->whenLoaded('occurrences', fn () => $this->occurrenceRows()),
        ];
    }

    /**
     * What the schedule generated, newest first, with enough of each record
     * to list and link it.
     *
     * @return list<array<string, mixed>>
     */
    private function occurrenceRows(): array
    {
        return $this->occurrences
            ->sortByDesc('scheduled_for')
            ->map(function ($row): array {
                $record = $row->record;
                $isBill = $record instanceof Bill;

                return [
                    'id' => (int) $row->id,
                    'scheduled_for' => substr((string) $row->scheduled_for, 0, 10),
                    'record_type' => $isBill ? 'bill' : 'expense',
                    'record_id' => (int) $row->record_id,
                    'number' => $isBill ? (string) $record->number : null,
                    'status' => $isBill ? (string) $record->status : null,
                    'amount' => $record === null ? null : (int) ($isBill ? $record->total : $record->amount),
                ];
            })
            ->values()
            ->all();
    }
}
