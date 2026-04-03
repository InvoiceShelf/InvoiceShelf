<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminUserUpdateRequest;
use App\Http\Resources\UserResource;
use App\Models\ImpersonationLog;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class UsersController extends Controller
{
    public function index(Request $request)
    {
        $limit = $request->has('limit') ? $request->limit : 10;

        $users = User::with('companies')
            ->applyFilters($request->all())
            ->latest()
            ->paginate($limit);

        return UserResource::collection($users);
    }

    public function show(User $user)
    {
        $user->load('companies');

        return new UserResource($user);
    }

    public function update(AdminUserUpdateRequest $request, User $user)
    {
        $data = $request->only(['name', 'email', 'phone']);

        if ($request->filled('password')) {
            $data['password'] = $request->password;
        }

        $user->update($data);

        return new UserResource($user);
    }

    public function impersonate(Request $request, User $user)
    {
        $admin = $request->user();

        if ($admin->id === $user->id) {
            return response()->json([
                'error' => 'cannot_impersonate_self',
                'message' => 'You cannot impersonate yourself.',
            ], 422);
        }

        $token = $user->createToken(
            'impersonation-by-'.$admin->id,
            ['*'],
            now()->addHours(2),
        );

        $log = ImpersonationLog::create([
            'admin_id' => $admin->id,
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'token_id' => $token->accessToken->id,
        ]);

        return response()->json([
            'token' => $token->plainTextToken,
            'impersonation_log_id' => $log->id,
            'user' => new UserResource($user),
        ]);
    }

    public function stopImpersonating(Request $request)
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken && str_starts_with($token->name, 'impersonation-by-')) {
            $log = ImpersonationLog::where('token_id', $token->id)
                ->whereNull('stopped_at')
                ->first();

            if ($log) {
                $log->update(['stopped_at' => now()]);
            }

            $token->delete();

            return response()->json(['success' => true]);
        }

        return response()->json([
            'error' => 'not_impersonating',
            'message' => 'No active impersonation session.',
        ], 422);
    }
}
