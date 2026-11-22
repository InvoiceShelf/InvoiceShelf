<?php

namespace App\Platform\Notifications\Http\Controllers;

use App\Domains\Accounts\Models\Company;
use App\Platform\Http\Controller;
use App\Platform\Notifications\Application\CompanyNotificationDefaults;
use App\Platform\Notifications\Http\Requests\CompanyNotificationDefaultsRequest;
use App\Platform\Notifications\NotificationCatalogue;
use App\Platform\Notifications\NotificationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What the company's owner decided for everyone in it: which kinds of
 * notice are switched off, and where the rest start for each member.
 */
class CompanyNotificationDefaultsController extends Controller
{
    public function __construct(
        private readonly NotificationCatalogue $catalogue,
        private readonly CompanyNotificationDefaults $defaults,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $company = $this->company($request);

        return response()->json(['data' => $this->present($company)]);
    }

    public function update(CompanyNotificationDefaultsRequest $request): JsonResponse
    {
        $company = $this->company($request);

        $this->defaults->update((int) $company->id, $request->choices());

        return response()->json(['data' => $this->present($company)]);
    }

    private function company(Request $request): Company
    {
        $company = Company::query()->find($request->header('company'));

        $this->authorize('manage company', $company);

        return $company;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function present(Company $company): array
    {
        $defaults = $this->defaults->for((int) $company->id);

        $types = array_filter($this->catalogue->all(), fn (NotificationType $type): bool => ! $type->platform);

        return array_values(array_map(fn (NotificationType $type): array => [
            'type' => $type->key,
            'group' => $type->group,
            'personal' => $type->isPersonal(),
            ...$defaults[$type->key],
        ], $types));
    }
}
