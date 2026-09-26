<?php

namespace App\Domains\Accounts\Http\Controllers\Admin;

use App\Domains\Accounts\Application\MemberService;
use App\Domains\Accounts\Http\Requests\AdminUserRequest;
use App\Domains\Accounts\Http\Resources\UserResource;
use App\Domains\Accounts\Models\ImpersonationLog;
use App\Domains\Accounts\Models\User;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class UsersController extends Controller
{
    public function __construct(private readonly MemberService $members) {}

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

    /**
     * A new account, in the companies listed (possibly none) with a role in
     * each. Without a company it lands on the page for making its first one.
     */
    public function store(AdminUserRequest $request)
    {
        $user = DB::transaction(fn () => $this->members->create(
            $request->accountAttributes() + ['creator_id' => $request->user()->id, 'role' => 'user'],
            $request->validated('companies', []),
        ));

        return (new UserResource($user->fresh('companies')))->response()->setStatusCode(201);
    }

    /**
     * The account, and when companies are sent, every company it belongs to
     * with its role there; companies left off the list are left.
     */
    public function update(AdminUserRequest $request, User $user)
    {
        DB::transaction(function () use ($request, $user): void {
            if (! $request->has('companies')) {
                $user->update($request->accountAttributes());

                return;
            }

            $companies = $request->validated('companies', []);
            $managed = $user->companies()->pluck('companies.id')
                ->merge(array_column($companies, 'id'))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            $this->members->update($user, $request->accountAttributes(), $companies, $managed);
        });

        return new UserResource($user->fresh('companies'));
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
