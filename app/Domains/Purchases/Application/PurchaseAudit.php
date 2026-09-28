<?php

namespace App\Domains\Purchases\Application;

use App\Domains\Accounts\Models\User;
use App\Domains\Purchases\Models\PurchaseActivity;
use App\Domains\Purchases\Models\Supplier;
use Illuminate\Database\Eloquent\Model;

final class PurchaseAudit
{
    public static function record(Model $record, string $action, ?int $actorId, array $details = []): void
    {
        PurchaseActivity::query()->create([
            'company_id' => $record->company_id,
            'supplier_id' => $record instanceof Supplier ? $record->id : $record->supplier_id,
            'subject_type' => $record->getMorphClass(), 'subject_id' => $record->id,
            'actor_id' => $actorId, 'action' => $action, 'details' => [...$details, 'actor_name' => $actorId ? User::query()->whereKey($actorId)->value('name') : null],
        ]);
    }
}
