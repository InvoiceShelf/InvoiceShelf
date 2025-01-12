<?php

namespace App\Http\Controllers\V1\Admin\Estimate;

use App\Http\Controllers\Controller;
use App\Http\Resources\EstimateResource;
use App\Models\CompanySetting;
use App\Models\Estimate;
use App\Services\SerialNumberFormatter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Vinkla\Hashids\Facades\Hashids;

class CloneEstimateController extends Controller
{
    /**
     * Mail a specific invoice to the corresponding customer's email address.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(Request $request, Estimate $estimate)
    {
        $this->authorize('create', Estimate::class);

        $date = Carbon::now();

        $serial = (new SerialNumberFormatter)
            ->setModel($estimate)
            ->setCompany($estimate->company_id)
            ->setCustomer($estimate->customer_id)
            ->setNextNumbers();

        $due_date = null;
        $dueDateEnabled = CompanySetting::getSetting(
            'estimate_set_expiry_date_automatically',
            $request->header('company')
        );

        if ($dueDateEnabled === 'YES') {
            $dueDateDays = intval(CompanySetting::getSetting(
                'estimate_expiry_date_days',
                $request->header('company')
            ));
            $due_date = Carbon::now()->addDays($dueDateDays)->format('Y-m-d');
        }

        $exchange_rate = $estimate->exchange_rate;

        $newEstimate = Estimate::create([
            'estimate_date' => $date->format('Y-m-d'),
            'expiry_date' => $due_date,
            'estimate_number' => $serial->getNextNumber(),
            'sequence_number' => $serial->nextSequenceNumber,
            'customer_sequence_number' => $serial->nextCustomerSequenceNumber,
            'reference_number' => $estimate->reference_number,
            'customer_id' => $estimate->customer_id,
            'company_id' => $request->header('company'),
            'template_name' => $estimate->template_name,
            'status' => Estimate::STATUS_DRAFT,
            'sub_total' => $estimate->sub_total,
            'discount' => $estimate->discount,
            'discount_type' => $estimate->discount_type,
            'discount_val' => $estimate->discount_val,
            'total' => $estimate->total,
            'due_amount' => $estimate->total,
            'tax_per_item' => $estimate->tax_per_item,
            'discount_per_item' => $estimate->discount_per_item,
            'tax' => $estimate->tax,
            'notes' => $estimate->notes,
            'exchange_rate' => $exchange_rate,
            'base_total' => $estimate->total * $exchange_rate,
            'base_discount_val' => $estimate->discount_val * $exchange_rate,
            'base_sub_total' => $estimate->sub_total * $exchange_rate,
            'base_tax' => $estimate->tax * $exchange_rate,
            'base_due_amount' => $estimate->total * $exchange_rate,
            'currency_id' => $estimate->currency_id,
            'sales_tax_type' => $estimate->sales_tax_type,
            'sales_tax_address_type' => $estimate->sales_tax_address_type,
        ]);

        $newEstimate->unique_hash = Hashids::connection(Estimate::class)->encode($newEstimate->id);
        $newEstimate->save();
        $estimate->load('items.taxes');

        $estimateItems = $estimate->items->toArray();

        foreach ($estimateItems as $estimateItem) {
            $estimateItem['company_id'] = $request->header('company');
            $estimateItem['name'] = $estimateItem['name'];
            $estimateItem['exchange_rate'] = $exchange_rate;
            $estimateItem['base_price'] = $estimateItem['price'] * $exchange_rate;
            $estimateItem['base_discount_val'] = $estimateItem['discount_val'] * $exchange_rate;
            $estimateItem['base_tax'] = $estimateItem['tax'] * $exchange_rate;
            $estimateItem['base_total'] = $estimateItem['total'] * $exchange_rate;

            $item = $newEstimate->items()->create($estimateItem);

            if (array_key_exists('taxes', $estimateItem) && $estimateItem['taxes']) {
                foreach ($estimateItem['taxes'] as $tax) {
                    $tax['company_id'] = $request->header('company');

                    if ($tax['amount']) {
                        $item->taxes()->create($tax);
                    }
                }
            }
        }

        if ($estimate->taxes) {
            foreach ($estimate->taxes->toArray() as $tax) {
                $tax['company_id'] = $request->header('company');
                $newEstimate->taxes()->create($tax);
            }
        }

        if ($estimate->fields()->exists()) {
            $customFields = [];

            foreach ($estimate->fields as $data) {
                $customFields[] = [
                    'id' => $data->custom_field_id,
                    'value' => $data->defaultAnswer,
                ];
            }

            $newEstimate->addCustomFields($customFields);
        }

        return new EstimateResource($newEstimate);
    }
}
