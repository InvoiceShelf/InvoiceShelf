<?php

namespace App\Domains\Accounts\Http\Controllers\Company;

use App\Domains\Accounts\Application\InvitationService;
use App\Domains\Accounts\Http\Resources\CompanyInvitationResource;
use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanyInvitation;
use App\Domains\Accounts\Models\User;
use App\Platform\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A company's pending invitations. Inviting someone is adding a member, so the
 * same rule applies as for members: only the company's owner may do it.
 */
class InvitationController extends Controller
{
    public function __construct(
        private readonly InvitationService $invitationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $company = Company::find($request->header('company'));

        $invitations = CompanyInvitation::where('company_id', $company->id)
            ->pending()
            ->with(['role', 'invitedBy'])
            ->latest()
            ->get();

        return response()->json([
            'invitations' => CompanyInvitationResource::collection($invitations),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $company = Company::find($request->header('company'));

        // The role has to be one of this company's own
        $request->validate([
            'email' => 'required|email',
            'role_id' => ['required', Rule::exists('roles', 'id')->where('scope', $company->id)],
        ]);

        $invitation = $this->invitationService->invite(
            $company,
            $request->email,
            $request->role_id,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'invitation' => new CompanyInvitationResource($invitation->load(['company', 'role', 'invitedBy'])),
        ]);
    }

    public function destroy(Request $request, CompanyInvitation $companyInvitation): JsonResponse
    {
        $this->authorize('create', User::class);

        // Only an invitation from the company in the header
        abort_unless((int) $companyInvitation->company_id === (int) $request->header('company'), 404);

        if ($companyInvitation->status !== CompanyInvitation::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending invitations can be cancelled.',
            ], 422);
        }

        $companyInvitation->delete();

        return response()->json(['success' => true]);
    }
}
