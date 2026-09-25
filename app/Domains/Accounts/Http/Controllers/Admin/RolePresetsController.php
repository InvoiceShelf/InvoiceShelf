<?php

namespace App\Domains\Accounts\Http\Controllers\Admin;

use App\Domains\Accounts\Application\Exceptions\RolePresetInUse;
use App\Domains\Accounts\Application\Exceptions\RolePresetLocked;
use App\Domains\Accounts\Application\RolePresetService;
use App\Domains\Accounts\Http\Requests\RolePresetRequest;
use App\Domains\Accounts\Http\Resources\RolePresetResource;
use App\Domains\Accounts\Models\RolePreset;
use App\Platform\Http\Controller;
use Illuminate\Http\JsonResponse;

/**
 * The super administrator's role presets. Every change reaches every company.
 */
class RolePresetsController extends Controller
{
    public function __construct(private readonly RolePresetService $presets) {}

    public function index()
    {
        return RolePresetResource::collection($this->presets->all()->filter(fn (RolePreset $preset) => $preset->exists));
    }

    public function store(RolePresetRequest $request)
    {
        $preset = $this->presets->create($request->validated('title'), $request->validated('abilities'));

        return (new RolePresetResource($preset))->response()->setStatusCode(201);
    }

    public function update(RolePresetRequest $request, RolePreset $rolePreset)
    {
        try {
            $this->presets->update($rolePreset, $request->validated('title'), $request->validated('abilities'));
        } catch (RolePresetLocked $e) {
            return respondJson('role_preset_locked', $e->getMessage());
        }

        return new RolePresetResource($rolePreset);
    }

    public function destroy(RolePreset $rolePreset): JsonResponse
    {
        try {
            $this->presets->delete($rolePreset);
        } catch (RolePresetLocked $e) {
            return respondJson('role_preset_locked', $e->getMessage());
        } catch (RolePresetInUse $e) {
            return respondJson('role_preset_in_use', $e->getMessage());
        }

        return response()->json(['success' => true]);
    }
}
