<?php

namespace App\Http\Controllers\V1\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Currency;
use Illuminate\Support\Arr;

class UpdateCompanySettingsController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\UpdateSettingsRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(UpdateSettingsRequest $request)
    {
        $company = Company::find($request->header('company'));
        $this->authorize('manage company', $company);

        $data = $request->settings;

        if (
            Arr::exists($data, 'currency') &&
            (CompanySetting::getSetting('currency', $company->id) !== $data['currency']) &&
            $company->hasTransactions()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update company currency after transactions are created.',
            ]);
        }

        $companySettings = CompanySetting::setSettings($data, $request->header('company'));

        $currency = Currency::find($data['currency']);
        $currency->update([
            'thousand_separator' => $data['currency_thousand_separator'],
            'decimal_separator' => $data['currency_decimal_separator'],
        ]);

        return response()->json([
            'success' => true,
        ]);
    }
}
