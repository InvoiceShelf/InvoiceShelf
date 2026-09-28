<?php

namespace App\Domains\Reporting\Http\Controllers;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Reporting\Http\Requests\PurchaseReportPdfRequest;
use App\Domains\Reporting\Queries\PurchasesQuery;
use App\Platform\Http\Controller;
use App\Platform\Pdf\Facades\Pdf;
use App\Platform\Pdf\Rendering\PdfPageSetup;
use App\Platform\Pdf\Rendering\PdfTemplateUtils;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\App;

class PurchasesReportController extends Controller
{
    /**
     * Render the purchases report as a PDF (or an HTML preview) for the
     * company named by the hash in the URL.
     */
    public function __invoke(PurchaseReportPdfRequest $request, string $hash, PurchasesQuery $query)
    {
        $company = $request->company();

        App::setLocale(CompanySetting::getSetting('language', $company->id) ?: config('app.locale'));

        $supplierId = $request->integer('supplier_id') ?: null;

        $report = $query->report(
            $company->id,
            $request->validated('from_date'),
            $request->validated('to_date'),
            $supplierId,
        );

        $pattern = CompanySetting::getSetting('carbon_date_format', $company->id) ?: 'Y-m-d';

        $data = [
            'company' => $company,
            'logo' => $company->logo_path,
            'currency' => $report['currency'],
            'report' => $report,
            // The query has already found the supplier; the view reads its name.
            'supplier' => $report['supplier'] ? (object) $report['supplier'] : null,
            'from_date' => CarbonImmutable::parse($request->validated('from_date'))->translatedFormat($pattern),
            'to_date' => CarbonImmutable::parse($request->validated('to_date'))->translatedFormat($pattern),
            'as_of_date' => CarbonImmutable::parse($report['payables']['as_of_date'])->translatedFormat($pattern),
            'date_pattern' => $pattern,
        ];

        $view = PdfTemplateUtils::resolveView('reports', 'purchases');
        view()->share($data);

        if ($request->exists('preview')) {
            return view($view, $data);
        }

        $document = Pdf::loadView($view, [], PdfPageSetup::forReports());

        return $request->exists('download') ? $document->download() : $document->stream();
    }
}
