<?php

namespace App\Http\Controllers\Company\Members;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteMemberRequest;
use App\Http\Requests\MemberRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\MemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MembersController extends Controller
{
    public function __construct(
        private readonly MemberService $memberService,
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $limit = $request->has('limit') ? $request->limit : 10;

        $user = $request->user();

        $users = User::whereCompany()
            ->applyFilters($request->all())
            ->where('id', '<>', $user->id)
            ->latest()
            ->paginate($limit);

        return UserResource::collection($users)
            ->additional(['meta' => [
                'user_total_count' => User::whereCompany()->count(),
            ]]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return JsonResponse
     */
    public function store(MemberRequest $request)
    {
        $this->authorize('create', User::class);

        $user = $this->memberService->create($request);

        return new UserResource($user);
    }

    /**
     * Display the specified resource.
     *
     * @return JsonResponse
     */
    public function show(User $user)
    {
        $this->authorize('view', $user);

        return new UserResource($user);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return JsonResponse
     */
    public function update(MemberRequest $request, User $user)
    {
        $this->authorize('update', $user);

        $this->memberService->update($user, $request);

        return new UserResource($user);
    }

    /**
     * Display a listing of the resource.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function delete(DeleteMemberRequest $request)
    {
        $this->authorize('delete multiple users', User::class);

        if ($request->users) {
            $this->memberService->delete($request->users);
        }

        return response()->json([
            'success' => true,
        ]);
    }
}
