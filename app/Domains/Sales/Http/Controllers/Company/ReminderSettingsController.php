<?php

namespace App\Domains\Sales\Http\Controllers\Company;

use App\Domains\Accounts\Models\Company;
use App\Domains\Sales\Application\ReminderSettings;
use App\Domains\Sales\Http\Requests\ReminderSettingsRequest;
use App\Platform\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The company's payment reminder settings, for its owner.
 */
class ReminderSettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $company = $this->company($request);

        return response()->json(['data' => ReminderSettings::for((int) $company->id)->toArray()]);
    }

    public function update(ReminderSettingsRequest $request): JsonResponse
    {
        $company = $this->company($request);

        ReminderSettings::save((int) $company->id, $request->validated());

        return response()->json(['data' => ReminderSettings::for((int) $company->id)->toArray()]);
    }

    private function company(Request $request): Company
    {
        $company = Company::query()->find($request->header('company'));

        $this->authorize('manage company', $company);

        return $company;
    }
}
