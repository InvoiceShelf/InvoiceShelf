<?php

namespace App\Domains\Accounts\Application;

use App\Domains\Accounts\Contracts\MemberReferencesCleaner;
use App\Domains\Accounts\Models\User;
use Illuminate\Support\Collection;
use Silber\Bouncer\BouncerFacade;

/**
 * Every write behind the member endpoints: filing a staff account, pointing it
 * at a set of companies, and erasing one outright.
 *
 * A submitted membership list is authoritative rather than additive — a company
 * left off the list is detached — and each entry names the single role the
 * account is to hold inside that company, displacing whatever it held there
 * before.
 *
 * Roles are handed out inside each company's own Bouncer scope, which is put
 * back afterwards, so this works the same from a company request and from the
 * super administrator's, which has none. Leaving a company takes the role held
 * there with it.
 */
class MemberService
{
    public function __construct(
        private readonly MemberReferencesCleaner $memberReferencesCleaner,
        private readonly AccessRevoker $accessRevoker,
    ) {}

    /**
     * File a new account and place it in the listed companies.
     *
     * Its language preference is written as the sentinel `default`, so the new
     * member reads the app in whatever language their company is set to rather
     * than in a frozen copy of the language whoever added them was using.
     *
     * @param  array<string, mixed>  $attributes
     * @param  iterable<int, array{id: int, role: string}>  $companies
     */
    public function create(array $attributes, iterable $companies): User
    {
        $member = User::create($attributes);

        $member->setSettings(['language' => 'default']);

        $memberships = collect($companies);

        $member->companies()->sync($memberships->pluck('id'));

        $this->grantRoles($member, $memberships);

        return $member;
    }

    /**
     * Overwrite an account and re-point it at the listed companies.
     *
     * Only memberships in the companies the caller manages are replaced: one of
     * those left off the list is detached, and the roles held there are left
     * behind, since the role sync below only visits companies still on the
     * list. Memberships in any other company stay as they are.
     *
     * @param  array<string, mixed>  $attributes
     * @param  iterable<int, array{id: int, role: string}>  $companies
     * @param  array<int, int>  $managedCompanyIds
     */
    public function update(User $user, array $attributes, iterable $companies, array $managedCompanyIds): User
    {
        $user->update($attributes);

        $memberships = collect($companies);

        $elsewhere = $user->companies()
            ->whereNotIn('companies.id', $managedCompanyIds)
            ->pluck('companies.id');

        $changes = $user->companies()->sync($elsewhere->merge($memberships->pluck('id'))->unique()->values());

        // Access granted to outside clients inside a company the account just
        // left ends with the membership.
        foreach ($changes['detached'] as $companyId) {
            $this->accessRevoker->revokeCompany($user->id, (int) $companyId);

            // Or an invitation back into the company would restore the old
            // role next to the new one.
            BouncerFacade::scope()->onceTo((int) $companyId, function () use ($user): void {
                BouncerFacade::sync($user)->roles([]);
            });
        }

        $this->grantRoles($user, $memberships);

        return $user;
    }

    /**
     * Erase the named accounts, one after another.
     *
     * An id naming nobody is skipped rather than reported. Everything the
     * account authored outlives it: invoices, estimates, contacts, recurring
     * invoices, expenses, payments and catalog entries are left standing with
     * no author against them, and only the preferences rows and the account
     * itself actually go.
     *
     * @param  array<int, int|string>  $ids
     */
    public function delete(array $ids): bool
    {
        foreach ($ids as $id) {
            $member = User::find($id);

            if ($member === null) {
                continue;
            }

            $this->memberReferencesCleaner->clear($member);

            $this->accessRevoker->revokeUser($member->id);

            if ($member->settings()->exists()) {
                $member->settings()->delete();
            }

            $member->delete();
        }

        return true;
    }

    /**
     * Give the account exactly the one role each company named, discarding any
     * role it already held in that company.
     *
     * @param  Collection<int, array{id: int, role: string}>  $memberships
     */
    private function grantRoles(User $member, Collection $memberships): void
    {
        foreach ($memberships as $membership) {
            BouncerFacade::scope()->onceTo((int) $membership['id'], function () use ($member, $membership): void {
                BouncerFacade::sync($member)->roles([$membership['role']]);
            });
        }

        BouncerFacade::refresh();
    }
}
