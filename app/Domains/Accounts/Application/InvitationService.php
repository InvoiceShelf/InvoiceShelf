<?php

namespace App\Domains\Accounts\Application;

use App\Domains\Accounts\Contracts\CompanyInvitationSender;
use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanyInvitation;
use App\Domains\Accounts\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Silber\Bouncer\Database\Role;

class InvitationService
{
    public function __construct(
        private readonly CompanyInvitationSender $companyInvitationSender,
        private readonly UserCompanyAccessService $companyAccess,
    ) {}

    /**
     * Invite a user to a company by email with one or more roles.
     */
    public function invite(Company $company, string $email, array $roleIds, User $invitedBy): CompanyInvitation
    {
        $roleIds = collect($roleIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $roleCount = Role::query()
            ->withoutGlobalScopes()
            ->where('scope', $company->id)
            ->whereIn('id', $roleIds)
            ->count();

        if ($roleIds === [] || $roleCount !== count($roleIds)) {
            throw ValidationException::withMessages([
                'role_ids' => ['Every selected role must belong to this company.'],
            ]);
        }

        // Check for existing pending invitation
        $existing = CompanyInvitation::where('company_id', $company->id)
            ->where('email', $email)
            ->pending()
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'email' => ['An invitation is already pending for this email.'],
            ]);
        }

        // Check if user is already a member
        $existingUser = User::where('email', $email)->first();
        if ($existingUser && $existingUser->belongsToCompany($company->id)) {
            throw ValidationException::withMessages([
                'email' => ['This user is already a member of this company.'],
            ]);
        }

        $invitation = CompanyInvitation::create([
            'company_id' => $company->id,
            'user_id' => $existingUser?->id,
            'email' => $email,
            // Keep role_id for compatibility with existing records and
            // readers; role_ids is the complete assignment.
            'role_id' => $roleIds[0],
            'role_ids' => $roleIds,
            'token' => Str::random(64),
            'status' => CompanyInvitation::STATUS_PENDING,
            'invited_by' => $invitedBy->id,
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $invitation->load(['company', 'role', 'invitedBy']);

        try {
            $this->companyInvitationSender->send($invitation);
        } catch (\Exception $e) {
            \Log::warning('Failed to send invitation email to '.$email.': '.$e->getMessage());
        }

        return $invitation;
    }

    /**
     * Accept a pending invitation and add the user to the company.
     */
    public function accept(CompanyInvitation $invitation, User $user): void
    {
        $this->assertAddressedTo($invitation, $user);

        if (! $invitation->isPending()) {
            throw ValidationException::withMessages([
                'invitation' => ['This invitation is no longer valid.'],
            ]);
        }

        if ($this->companyAccess->isRestrictedFromCompany($user, (int) $invitation->company_id)) {
            throw ValidationException::withMessages([
                'invitation' => ['You cannot accept an invitation to a restricted company.'],
            ]);
        }

        // Validate the stored role set before changing the user's membership.
        $roleIds = collect($invitation->role_ids ?: [$invitation->role_id])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $roles = Role::withoutGlobalScopes()
            ->where('scope', $invitation->company_id)
            ->whereIn('id', $roleIds)
            ->get();

        if ($roleIds->isEmpty() || $roles->count() !== $roleIds->count()) {
            throw ValidationException::withMessages([
                'invitation' => ['This invitation contains an invalid company role.'],
            ]);
        }

        DB::transaction(function () use ($invitation, $roles, $user): void {
            // Add user to company and assign every role in that company's scope.
            $user->companies()->attach($invitation->company_id);
            $this->companyAccess->replaceCompanyRoles(
                $user,
                (int) $invitation->company_id,
                $roles->pluck('name')->all(),
            );

            $invitation->update([
                'status' => CompanyInvitation::STATUS_ACCEPTED,
                'user_id' => $user->id,
            ]);
        });
    }

    /**
     * Decline a pending invitation.
     */
    public function decline(CompanyInvitation $invitation, User $user): void
    {
        $this->assertAddressedTo($invitation, $user);

        if (! $invitation->isPending()) {
            throw ValidationException::withMessages([
                'invitation' => ['This invitation is no longer valid.'],
            ]);
        }

        $invitation->update([
            'status' => CompanyInvitation::STATUS_DECLINED,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Get all pending invitations for a user (by user_id or email).
     */
    public function getPendingForUser(User $user): Collection
    {
        return CompanyInvitation::forUser($user)
            ->pending()
            ->with(['company', 'role', 'invitedBy'])
            ->get();
    }

    /**
     * An invitation is answered by the person it was sent to, and nobody else:
     * holding its token is not enough.
     */
    private function assertAddressedTo(CompanyInvitation $invitation, User $user): void
    {
        if (strcasecmp((string) $user->email, (string) $invitation->email) !== 0) {
            throw new AuthorizationException('This invitation was sent to someone else.');
        }
    }
}
