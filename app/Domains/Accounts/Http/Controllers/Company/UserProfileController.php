<?php

namespace App\Domains\Accounts\Http\Controllers\Company;

use App\Domains\Accounts\Contracts\UserAvatarManager;
use App\Domains\Accounts\Http\Requests\AvatarRequest;
use App\Domains\Accounts\Http\Requests\GetSettingsRequest;
use App\Domains\Accounts\Http\Requests\ProfileRequest;
use App\Domains\Accounts\Http\Requests\UpdateSettingsRequest;
use App\Domains\Accounts\Http\Resources\UserResource;
use App\Domains\Metadata\Contracts\CustomFieldValueWriter;
use App\Platform\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    public function __construct(
        private readonly UserAvatarManager $userAvatarManager,
        private readonly CustomFieldValueWriter $customFieldValueWriter,
    ) {}

    public function show(Request $request)
    {
        return new UserResource($request->user());
    }

    public function update(ProfileRequest $request)
    {
        $account = $request->user();

        $account->update($request->getProfilePayload());

        if ($customFields = $request->input('customFields')) {
            $this->customFieldValueWriter->update($account, $customFields, $request->header('company'));
        }

        return new UserResource($account->refresh());
    }

    /**
     * Attach, swap or drop the caller's profile picture.
     *
     * Three things can arrive on one call and each is handled in turn, so a
     * payload carrying both a removal flag and a picture ends up with the
     * picture, and a payload carrying both transports ends up with whatever
     * came in as base64. Only the two picture branches check that somebody is
     * actually signed in; the removal branch does not. Kept as it stands.
     */
    public function uploadAvatar(AvatarRequest $request)
    {
        $user = $request->user();

        if ($request->is_admin_avatar_removed ?? false) {
            $this->userAvatarManager->clear($user);
        }
        if ($user && $request->hasFile('admin_avatar')) {
            $file = $request->file('admin_avatar');
            $this->userAvatarManager->replaceFile(
                $user,
                $file->getRealPath(),
                $file->getClientOriginalName(),
            );
        }

        if ($user !== null && $request->has('avatar')) {
            $encoded = $request->avatar;
            $data = json_decode($encoded);
            $this->userAvatarManager->replaceBase64($user, $data->data, $data->name);
        }

        return new UserResource($user);
    }

    public function showSettings(GetSettingsRequest $request): JsonResponse
    {
        $user = $request->user();

        return response()->json($user->getSettings((array) $request->settings));
    }

    public function updateSettings(UpdateSettingsRequest $request): JsonResponse
    {
        $request->user()->setSettings($request->settings);

        return response()->json(['success' => true]);
    }
}
