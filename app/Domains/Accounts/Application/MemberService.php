<?php

namespace App\Domains\Accounts\Application;

use App\Domains\Accounts\Contracts\MemberReferencesCleaner;
use App\Domains\Accounts\Models\User;
use Illuminate\Support\Collection;

/**
 * Every write behind the member endpoints: filing a staff account, pointing it
 * at a set of companies, and erasing one outright.
 *
 * A submitted membership list is authoritative rather than additive — a company
 * left off the list is detached — and each entry names the roles the account is
 * to hold inside that company, displacing whatever it held there before.
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
        private readonly UserCompanyAccessService $companyAccess,
    ) {}

    /**
     * File a new account and place it in the listed companies.
     *
     * Its language preference is written as the sentinel `default`, so the new
     * member reads the app in whatever language their company is set to rather
     * than in a frozen copy of the language whoever added them was using.
     *
     * @param  array<string, mixed>  $attributes
     * @param  iterable<int, array{id: int, roles?: list<string>, role?: string, include_global_roles?: bool}>  $companies
     * @param  list<string>  $globalRoles
     * @param  list<int>  $restrictedCompanyIds
     */
    public function create(array $attributes, iterable $companies, array $globalRoles = [], array $restrictedCompanyIds = []): User
    {
        $member = User::create($attributes);

        $member->setSettings(['language' => 'default']);

        $memberships = collect($companies);

        $member->companies()->sync($this->membershipPivotValues($memberships, collect()));

        $this->grantRoles($member, $memberships);
        $this->companyAccess->syncUserAccess($member, $globalRoles, $restrictedCompanyIds);

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
     * @param  iterable<int, array{id: int, roles?: list<string>, role?: string, include_global_roles?: bool}>  $companies
     * @param  array<int, int>  $managedCompanyIds
     * @param  list<string>|null  $globalRoles
     * @param  list<int>|null  $restrictedCompanyIds
     */
    public function update(
        User $user,
        array $attributes,
        iterable $companies,
        array $managedCompanyIds,
        ?array $globalRoles = null,
        ?array $restrictedCompanyIds = null,
    ): User {
        $user->update($attributes);

        $memberships = collect($companies);

        $elsewhere = $user->companies()
            ->whereNotIn('companies.id', $managedCompanyIds)
            ->pluck('companies.id');

        $existingCombinations = $user->companies()
            ->pluck('user_company.include_global_roles', 'companies.id')
            ->map(fn (mixed $value): bool => (bool) $value);

        $preserved = $elsewhere->mapWithKeys(fn (mixed $companyId): array => [(int) $companyId => []]);
        $changes = $user->companies()->sync(
            $this->membershipPivotValues($memberships, $existingCombinations) + $preserved->all()
        );

        // Access granted to outside clients inside a company the account just
        // left ends with the membership.
        foreach ($changes['detached'] as $companyId) {
            $this->accessRevoker->revokeCompany($user->id, (int) $companyId);

            // Or an invitation back into the company would restore the old
            // role next to the new one.
            $this->companyAccess->replaceCompanyRoles($user, (int) $companyId, []);
        }

        $this->grantRoles($user, $memberships);
        $this->companyAccess->syncUserAccess($user, $globalRoles, $restrictedCompanyIds);

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
     * Give the account exactly the roles each company named, discarding any
     * roles it already held in that company.
     *
     * @param  Collection<int, array{id: int, roles?: list<string>, role?: string}>  $memberships
     */
    private function grantRoles(User $member, Collection $memberships): void
    {
        foreach ($memberships as $membership) {
            $this->companyAccess->replaceCompanyRoles(
                $member,
                (int) $membership['id'],
                $membership['roles'] ?? (isset($membership['role']) ? [$membership['role']] : []),
            );
        }
    }

    /**
     * Convert the form's memberships into the pivot shape accepted by sync.
     * Missing values intentionally default to false for new and administration
     * updates. Updates preserve an existing value when an older client omits
     * the field, while new memberships default to false.
     *
     * @param  Collection<int, array{id: int, include_global_roles?: bool}>  $memberships
     * @param  Collection<int, bool>  $existingCombinations
     * @return array<int, array{include_global_roles: bool}>
     */
    private function membershipPivotValues(Collection $memberships, Collection $existingCombinations): array
    {
        return $memberships->mapWithKeys(function (array $membership) use ($existingCombinations): array {
            $companyId = (int) $membership['id'];

            return [
                $companyId => [
                    'include_global_roles' => array_key_exists('include_global_roles', $membership)
                        ? (bool) $membership['include_global_roles']
                        : (bool) $existingCombinations->get($companyId, false),
                ],
            ];
        })->all();
    }
}
