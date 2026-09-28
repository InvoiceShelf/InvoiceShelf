<?php

namespace App\Domains\Reporting\Queries;

use App\Domains\Sales\Models\Invoice;
use App\Domains\Taxation\Models\Tax;
use App\Domains\Taxation\Models\TaxType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Tax on sales and on purchases over a period, by document date and whatever
 * has been paid: what the documents say, from their stored tax snapshots.
 *
 * Sales are every issued invoice and credit note (a credit note's tax is
 * negative), whether paid or not. Purchases are expenses and recorded bills
 * less supplier credits, as the purchases report counts them. Payments,
 * refunds and allocations never add tax of their own.
 */
class TaxSummaryQuery
{
    public function __construct(private readonly PurchasesQuery $purchases) {}

    /**
     * @return array{sales: Collection<int, Tax>, purchases: Collection<int, Tax>}
     */
    public function report(int $companyId, string $from, string $to): array
    {
        return [
            'sales' => $this->templateRows($this->salesRows($companyId, $from, $to)),
            'purchases' => $this->templateRows($this->purchases->costs($companyId, $from, $to)['taxes']),
        ];
    }

    /**
     * Sales tax per tax type, name and rate, in the base currency. A tax row
     * may hang off the invoice or off one of its lines.
     *
     * @return list<array{tax_type_id: int|null, name: string, percent: mixed, amount: int}>
     */
    private function salesRows(int $companyId, string $from, string $to): array
    {
        $issued = fn (Builder $query) => $query
            ->where('company_id', $companyId)
            ->where('status', '!=', Invoice::STATUS_DRAFT)
            ->whereIn('type', [Invoice::TYPE_INVOICE, Invoice::TYPE_CREDIT_NOTE])
            ->where('invoice_date', '>=', $from)
            ->where('invoice_date', '<', CarbonImmutable::parse($to)->addDay()->toDateString());

        $taxes = Tax::query()
            ->where('company_id', $companyId)
            ->where(fn (Builder $query) => $query
                ->whereHas('invoice', $issued)
                ->orWhereHas('invoiceItem.invoice', $issued))
            ->get();

        $rows = [];

        foreach ($taxes as $tax) {
            $key = $tax->tax_type_id.':'.$tax->name.':'.$tax->percent;
            $rows[$key] ??= [
                'tax_type_id' => $tax->tax_type_id,
                'name' => $tax->name,
                'percent' => $tax->percent,
                'amount' => 0,
            ];
            $rows[$key]['amount'] += (int) $tax->base_amount;
        }

        return array_values($rows);
    }

    /**
     * The rows as the Tax models installed report templates expect, with the
     * `total_tax_amount` and `taxType` relation they read.
     */
    private function templateRows(array $rows): Collection
    {
        return collect($rows)->map(function (array $row): Tax {
            $tax = (new Tax)->forceFill([
                'tax_type_id' => $row['tax_type_id'],
                'name' => $row['name'],
                'percent' => $row['percent'],
                'total_tax_amount' => $row['amount'],
            ]);
            $tax->setRelation('taxType', (new TaxType)->forceFill([
                'id' => $row['tax_type_id'],
                'name' => $row['name'],
            ]));

            return $tax;
        });
    }
}
