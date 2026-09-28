<?php

namespace App\Domains\Purchases\Application;

use App\Domains\Purchases\Http\Requests\SupplierRequest;
use App\Domains\Purchases\Models\Supplier;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SupplierService
{
    public function __construct(private readonly PurchaseCustomFields $customFields) {}

    /**
     * Create a supplier, or update one under a row lock, together with its
     * custom field answers. The data is validated again here, so the service
     * holds its own rules whoever calls it.
     */
    public function save(?Supplier $supplier, int $companyId, ?int $actorId, array $data): Supplier
    {
        $data = Validator::make($data, SupplierRequest::rulesFor($companyId))->validate();

        return DB::transaction(function () use ($supplier, $companyId, $actorId, $data): Supplier {
            $record = $supplier
                ? PurchaseInputs::lockSupplier($companyId, $supplier->id)
                : new Supplier(['company_id' => $companyId, 'creator_id' => $actorId]);

            $answers = $this->customFields->resolve(
                $companyId,
                'Supplier',
                $data['customFields'] ?? [],
                $this->customFields->saved($record),
            );

            $record->fill(Arr::except($data, ['customFields']))->save();
            $this->customFields->save($record, $answers);

            return $record;
        });
    }
}
