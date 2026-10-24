<?php

namespace App\Platform\Notifications\Http\Controllers;

use App\Platform\Http\Controller;
use App\Platform\Notifications\Application\NotificationPreferences;
use App\Platform\Notifications\Http\Requests\NotificationPreferencesRequest;
use App\Platform\Notifications\NotificationCatalogue;
use App\Platform\Notifications\NotificationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The caller's own choice of where each kind of notice reaches them, as it
 * applies in the company in the header.
 */
class NotificationPreferencesController extends Controller
{
    public function __construct(
        private readonly NotificationCatalogue $catalogue,
        private readonly NotificationPreferences $preferences,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->present($request)]);
    }

    public function update(NotificationPreferencesRequest $request): JsonResponse
    {
        $this->preferences->update($request->user(), $request->choices());

        return response()->json(['data' => $this->present($request)]);
    }

    /**
     * Each type the caller can receive, with its group and their choice, in
     * catalogue order. Platform notices are listed for super admins only.
     *
     * @return list<array<string, mixed>>
     */
    private function present(Request $request): array
    {
        $user = $request->user();
        $company = $request->header('company');
        $choices = $this->preferences->for($user, $company ? (int) $company : null);

        $types = array_filter(
            $this->catalogue->all(),
            fn (NotificationType $type): bool => ! $type->platform || $user->isSuperAdmin(),
        );

        return array_values(array_map(fn (NotificationType $type): array => [
            'type' => $type->key,
            'group' => $type->group,
            'personal' => $type->isPersonal(),
            'enabled' => $choices[$type->key]['enabled'],
            'bell' => $choices[$type->key]['bell'],
            'mail' => $choices[$type->key]['mail'],
            'customised' => $choices[$type->key]['customised'],
        ], $types));
    }
}
