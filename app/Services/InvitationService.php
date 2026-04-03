<?php

namespace App\Services;

use App\Mail\CompanyInvitationMail;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Silber\Bouncer\BouncerFacade;
use Silber\Bouncer\Database\Role;

class InvitationService
{
    /**
     * Invite a user to a company by email with a specific role.
     */
    public function invite(Company $company, string $email, int $roleId, User $invitedBy): CompanyInvitation
    {
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
        if ($existingUser && $existingUser->hasCompany($company->id)) {
            throw ValidationException::withMessages([
                'email' => ['This user is already a member of this company.'],
            ]);
        }

        $invitation = CompanyInvitation::create([
            'company_id' => $company->id,
            'user_id' => $existingUser?->id,
            'email' => $email,
            'role_id' => $roleId,
            'token' => Str::random(64),
            'status' => CompanyInvitation::STATUS_PENDING,
            'invited_by' => $invitedBy->id,
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $invitation->load(['company', 'role', 'invitedBy']);

        try {
            Mail::to($email)->send(new CompanyInvitationMail($invitation));
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
        if (! $invitation->isPending()) {
            throw ValidationException::withMessages([
                'invitation' => ['This invitation is no longer valid.'],
            ]);
        }

        // Add user to company
        $user->companies()->attach($invitation->company_id);

        // Assign role scoped to the invitation's company
        $role = Role::withoutGlobalScopes()->find($invitation->role_id);
        BouncerFacade::scope()->to($invitation->company_id);
        $user->assign($role->name);

        // Update invitation
        $invitation->update([
            'status' => CompanyInvitation::STATUS_ACCEPTED,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Decline a pending invitation.
     */
    public function decline(CompanyInvitation $invitation, User $user): void
    {
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
}
