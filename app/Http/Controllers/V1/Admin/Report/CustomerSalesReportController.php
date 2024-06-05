<?php

namespace App\Http\Controllers\V1\Admin\Report;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Currency;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use PDF;

class CustomerSalesReportController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  string  $hash
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(Request $request, $hash)
    {
        $company = Company::where('unique_hash', $hash)->first();

        $this->authorize('view report', $company);

        $locale = CompanySetting::getSetting('language', $company->id);

        App::setLocale($locale);

        $start = Carbon::createFromFormat('Y-m-d', $request->from_date);
        $end = Carbon::createFromFormat('Y-m-d', $request->to_date);

        $customers = Customer::with(['invoices' => function ($query) use ($start, $end) {
            $query->whereBetween(
                'invoice_date',
                [$start->format('Y-m-d'), $end->format('Y-m-d')]
            );
        }])
            ->where('company_id', $company->id)
            ->applyInvoiceFilters($request->only(['from_date', 'to_date']))
            ->get();

        $totalAmount = 0;
        foreach ($customers as $customer) {
            $customerTotalAmount = 0;
            foreach ($customer->invoices as $invoice) {
                $customerTotalAmount += $invoice->base_total;
            }
            $customer->totalAmount = $customerTotalAmount;
            $totalAmount += $customerTotalAmount;
        }

        $dateFormat = CompanySetting::getSetting('carbon_date_format', $company->id);
        $from_date = Carbon::createFromFormat('Y-m-d', $request->from_date)->translatedFormat($dateFormat);
        $to_date = Carbon::createFromFormat('Y-m-d', $request->to_date)->translatedFormat($dateFormat);
        $currency = Currency::findOrFail(CompanySetting::getSetting('currency', $company->id));

        $colors = [
            'primary_text_color',
            'heading_text_color',
            'section_heading_text_color',
            'border_color',
            'body_text_color',
            'footer_text_color',
            'footer_total_color',
            'footer_bg_color',
            'date_text_color',
        ];

        $colorSettings = CompanySetting::whereIn('option', $colors)
            ->whereCompany($company->id)
            ->get();

        view()->share([
            'customers' => $customers,
            'totalAmount' => $totalAmount,
            'colorSettings' => $colorSettings,
            'company' => $company,
            'from_date' => $from_date,
            'to_date' => $to_date,
            'currency' => $currency,
        ]);

        $pdf = PDF::loadView('app.pdf.reports.sales-customers');

        if ($request->has('preview')) {
            return view('app.pdf.reports.sales-customers');
        }

        if ($request->has('download')) {
            return $pdf->download();
        }

        return $pdf->stream();
    }
}
