<?php

namespace App\Http\Controllers\V1\Admin\Report;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Currency;
use App\Models\Expense;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;

class ExpensesReportController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  string  $hash
     * @return View|Response
     */
    public function __invoke(Request $request, $hash)
    {
        $company = Company::where('unique_hash', $hash)->first();

        $this->authorize('view report', $company);

        $locale = CompanySetting::getSetting('language', $company->id);

        App::setLocale($locale);

        // Fetch individual expenses (filtered and ordered by date), then group by category
        $expenses = Expense::with('category')
            ->whereCompanyId($company->id)
            ->applyFilters($request->only(['from_date', 'to_date', 'expense_category_id']))
            ->orderBy('expense_date', 'asc')
            ->get();

        $totalAmount = $expenses->sum('base_amount');

        $grouped = $expenses->groupBy(function ($item) {
            return $item->category ? $item->category->name : trans('expenses.uncategorized');
        });

        $expenseGroups = collect();
        foreach ($grouped as $categoryName => $group) {
            $expenseGroups->push([
                'name' => $categoryName,
                'expenses' => $group,
                'total' => $group->sum('base_amount'),
            ]);
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
            'expenseGroups' => $expenseGroups,
            'colorSettings' => $colorSettings,
            'totalExpense' => $totalAmount,
            'company' => $company,
            'from_date' => $from_date,
            'to_date' => $to_date,
            'currency' => $currency,
        ]);
        $pdf = Pdf::loadView('app.pdf.reports.expenses');

        if ($request->has('preview')) {
            return view('app.pdf.reports.expenses');
        }

        if ($request->has('download')) {
            return $pdf->download();
        }

        return $pdf->stream();
    }
}
