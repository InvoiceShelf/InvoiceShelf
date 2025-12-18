<?php

namespace App\Http\Controllers\V1\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Services\InteracETransferService;
use Illuminate\Support\Arr;

class UpdateCompanySettingsController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\UpdateSettingsRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(UpdateSettingsRequest $request, InteracETransferService $interacService)
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

        CompanySetting::setSettings($data, $request->header('company'));

        $connectionStatus = null;

        if ($this->containsInteracSettings($data)) {
            $settings = $interacService->mergeSettingsFromPayload($company, $data);

            $connectionStatus = $interacService->testConnection($settings);
        }

        return response()->json([
            'success' => true,
            'connection_status' => $connectionStatus,
        ]);
    }

    protected function containsInteracSettings(array $settings): bool
    {
        return (bool) collect($settings)
            ->keys()
            ->first(fn ($key) => str_starts_with($key, 'interac_'));
    }
}
