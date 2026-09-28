<?php

namespace App\Domains\Reporting\Http\Controllers;

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Money\Models\Currency;
use App\Domains\Reporting\Http\Requests\ReportPeriodRequest;
use App\Domains\Reporting\Queries\TaxSummaryQuery;
use App\Platform\Http\Controller;
use App\Platform\Pdf\Facades\Pdf;
use App\Platform\Pdf\Rendering\PdfPageSetup;
use App\Platform\Pdf\Rendering\PdfTemplateUtils;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Silber\Bouncer\BouncerFacade;

/** Tax on issued sales and recorded purchases by document date, regardless of settlement. */
class TaxSummaryReportController extends Controller
{
    /**
     * Render the report for the company the hash names.
     *
     * @param  string  $hash
     */
    public function __invoke(ReportPeriodRequest $request, $hash, TaxSummaryQuery $query)
    {
        $company = $this->reportedCompany($hash);

        App::setLocale(CompanySetting::getSetting('language', $company->id));

        $window = $request->validated();
        $taxes = $query->report($company->id, $window['from_date'], $window['to_date']);
        $collected = $taxes['sales'];
        $paid = $taxes['purchases'];
        $collectedTotal = (int) $collected->sum('total_tax_amount');
        $paidTotal = (int) $paid->sum('total_tax_amount');

        view()->share([
            'taxTypes' => $collected,
            'totalTaxAmount' => $collectedTotal,
            'expenseTaxTypes' => $paid,
            'totalExpenseTaxAmount' => $paidTotal,
            'netTaxAmount' => $collectedTotal - $paidTotal,
        ] + $this->pageChrome($request, $company));

        return $this->emit($request, 'tax-summary');
    }

    /**
     * The company named by the hash, once the caller has been let through.
     *
     * Nothing upstream tells Bouncer which company to weigh abilities against:
     * these links carry no company header, and the report ability is stored
     * per company, so the unscoped check matched nothing and every report
     * answered 403. Pointing the scope at the company in the URL settles that
     * without widening access, because the policy still asks for membership.
     * The hash is an address, not a credential.
     *
     * @param  string  $hash
     */
    private function reportedCompany($hash): Company
    {
        $company = Company::query()->where('unique_hash', $hash)->firstOrFail();

        BouncerFacade::scope()->to($company->id);

        $this->authorize('view report', $company);

        return $company;
    }

    /**
     * What every report prints around its figures: the company and its logo,
     * the window in the company's own date format, and the currency the
     * amounts are stated in.
     *
     * @return array<string, mixed>
     */
    private function pageChrome(Request $request, Company $company): array
    {
        $pattern = CompanySetting::getSetting('carbon_date_format', $company->id);
        $opened = Carbon::createFromFormat('Y-m-d', $request->from_date)->translatedFormat($pattern);
        $closed = Carbon::createFromFormat('Y-m-d', $request->to_date)->translatedFormat($pattern);
        $currencyId = CompanySetting::getSetting('currency', $company->id);
        $currency = Currency::findOrFail($currencyId);

        return [
            'company' => $company,
            'logo' => $company->logo_path,
            'from_date' => $opened,
            'to_date' => $closed,
            'currency' => $currency,
        ];
    }

    /**
     * Hand the rendered report over in whichever of the three shapes the query
     * string asks for.
     *
     * Reports have no template chooser, so an override is a file of the same
     * name dropped into storage/app/templates/pdf/reports/, which the resolver
     * prefers over the built-in one.
     *
     * HTML previews share the same data and skip the unused PDF render.
     */
    private function emit(Request $request, string $design)
    {
        $design = PdfTemplateUtils::resolveView('reports', $design);

        if ($request->exists('preview')) {
            return view($design);
        }

        $document = Pdf::loadView($design, [], PdfPageSetup::forReports());

        return $request->exists('download') ? $document->download() : $document->stream();
    }
}
