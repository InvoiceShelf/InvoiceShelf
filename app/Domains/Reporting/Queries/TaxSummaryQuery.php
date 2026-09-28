<?php

namespace App\Domains\Reporting\Queries;

use App\Domains\Sales\Models\Invoice;
use App\Domains\Taxation\Models\Tax;
use App\Domains\Taxation\Models\TaxType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Aggregates stored tax snapshots, independently of payments and allocations. */
class TaxSummaryQuery
{
    public function __construct(private readonly PurchasesQuery $purchases) {}

    public function report(int $companyId, string $from, string $to): array
    {
        $issued = fn (Builder $query) => $query->where('company_id', $companyId)
            ->where('status', '!=', Invoice::STATUS_DRAFT)
            ->whereIn('type', [Invoice::TYPE_INVOICE, Invoice::TYPE_CREDIT_NOTE])
            ->where('invoice_date', '>=', $from)
            ->where('invoice_date', '<', CarbonImmutable::parse($to)->addDay()->toDateString());
        $sales = Tax::query()->where('company_id', $companyId)
            ->where(fn (Builder $query) => $query->whereHas('invoice', $issued)->orWhereHas('invoiceItem.invoice', $issued))->get();
        $rows = [];
        foreach ($sales as $tax) {
            $key = $tax->tax_type_id.':'.$tax->name.':'.$tax->percent;
            $rows[$key] ??= ['tax_type_id' => $tax->tax_type_id, 'name' => $tax->name, 'percent' => $tax->percent, 'amount' => 0];
            $rows[$key]['amount'] += (int) $tax->base_amount;
        }

        return [
            'sales' => $this->templateRows(array_values($rows)),
            'purchases' => $this->templateRows($this->purchases->costs($companyId, $from, $to)['taxes']),
        ];
    }

    /** Preserve the variables and taxType relation used by installed report templates. */
    private function templateRows(array $rows): Collection
    {
        return collect($rows)->map(function (array $row): Tax {
            $tax = (new Tax)->forceFill(['tax_type_id' => $row['tax_type_id'], 'name' => $row['name'], 'percent' => $row['percent'], 'total_tax_amount' => $row['amount']]);
            $tax->setRelation('taxType', (new TaxType)->forceFill(['id' => $row['tax_type_id'], 'name' => $row['name']]));

            return $tax;
        });
    }
}
