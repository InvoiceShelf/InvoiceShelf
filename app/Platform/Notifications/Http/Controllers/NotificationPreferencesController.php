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
 * The caller's own choice of where each kind of notice reaches them.
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
     * Each type with its group and the caller's choice, in catalogue order.
     *
     * @return list<array{type: string, group: string, personal: bool, bell: bool, mail: bool}>
     */
    private function present(Request $request): array
    {
        $choices = $this->preferences->for($request->user());

        return array_values(array_map(fn (NotificationType $type): array => [
            'type' => $type->key,
            'group' => $type->group,
            'personal' => $type->isPersonal(),
            'bell' => $choices[$type->key]['bell'],
            'mail' => $choices[$type->key]['mail'],
        ], $this->catalogue->all()));
    }
}
