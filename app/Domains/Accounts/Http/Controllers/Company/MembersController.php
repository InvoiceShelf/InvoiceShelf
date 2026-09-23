<?php

namespace App\Domains\Accounts\Http\Controllers\Company;

use App\Domains\Accounts\Application\MemberService;
use App\Domains\Accounts\Http\Requests\DeleteMemberRequest;
use App\Domains\Accounts\Http\Requests\MemberRequest;
use App\Domains\Accounts\Http\Resources\UserResource;
use App\Domains\Accounts\Models\User;
use App\Platform\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Owner-side administration of the staff accounts inside the active company.
 *
 * The controller settles who may act and hands everything else on: the form
 * request shapes the payload and the service owns every write. Ownership is
 * positional and re-read on each call, so the same person administers members
 * under one `company` header and is refused under the next.
 *
 * Note that the single-account routes bind their model installation-wide — an
 * id belonging to another tenant resolves perfectly well and is turned away by
 * the policy rather than by a missing row, which is a 403 where a 404 might be
 * expected. Kept as it stands.
 */
class MembersController extends Controller
{
    public function __construct(
        private readonly MemberService $memberService,
    ) {}

    /**
     * A page of colleagues, newest first.
     *
     * The requester is struck from the rows but still counted in the envelope,
     * so a company of three people shows two members underneath the number
     * three. Kept as it stands.
     *
     * Page size defaults to ten, and unlike the other listings there is no
     * sentinel for "everything" — a limit of `all` reaches the paginator as-is.
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $perPage = $request->has('limit') ? $request->limit : 10;
        $viewer = $request->user();

        $members = User::whereCompany()
            ->applyFilters($request->all())
            ->where('id', '<>', $viewer->id)
            ->latest()
            ->paginate($perPage);

        return UserResource::collection($members)->additional([
            'meta' => ['user_total_count' => User::whereCompany()->count()],
        ]);
    }

    /**
     * Open a staff account and place it in the companies the form listed.
     *
     * The gate weighs the active company; MemberRequest limits the listed
     * companies to those the caller owns.
     *
     * @return JsonResponse
     */
    public function store(MemberRequest $request)
    {
        $this->authorize('create', User::class);

        $member = $this->memberService->create(
            $request->getUserPayload(),
            $request->validated('companies'),
        );

        return new UserResource($member);
    }

    /**
     * One colleague, provided they share the active company with the caller.
     *
     * @return JsonResponse
     */
    public function show(User $member)
    {
        $this->authorize('view', $member);

        return new UserResource($member);
    }

    /**
     * Overwrite a colleague's account and re-point their memberships.
     *
     * @return JsonResponse
     */
    public function update(MemberRequest $request, User $member)
    {
        $this->authorize('update', $member);

        $this->memberService->update(
            $member,
            $request->getUserPayload(),
            $request->validated('companies'),
            $request->managedCompanyIds(),
        );

        return new UserResource($member);
    }

    /**
     * Erase a batch of accounts.
     *
     * The submitted ids were checked against the users table installation-wide,
     * then narrowed to members of the active company here, so an id belonging
     * to somebody else's tenant clears validation and is quietly dropped from
     * the batch — the call still answers success. Kept as it stands.
     *
     * The gate is the bulk ability rather than the per-account policy, so it
     * asks nothing about the individual targets; the narrowing above is what
     * keeps one company out of another's accounts.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function delete(DeleteMemberRequest $request)
    {
        $this->authorize('delete multiple users', User::class);

        $submitted = $request->users;

        if ($submitted) {
            $targets = User::whereCompany()
                ->whereIn('id', $submitted)
                ->pluck('id')
                ->toArray();

            if ($targets) {
                $this->memberService->delete($targets);
            }
        }

        return response()->json([
            'success' => true,
        ]);
    }
}
