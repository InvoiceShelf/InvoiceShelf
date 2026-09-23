<?php

namespace App\Domains\Accounts\Http\Controllers\Company;

use App\Domains\Accounts\Application\MemberVisibleSettings;
use App\Domains\Accounts\Http\Requests\GetSettingsRequest;
use App\Domains\Accounts\Http\Requests\UpdateSettingsRequest;
use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Accounts\Models\User;
use App\Platform\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Silber\Bouncer\BouncerFacade;

/**
 * The preference store of the company named by the request header, plus the
 * two questions that hang off it: whether the books have been opened, and who
 * the company now belongs to.
 *
 * Reading preferences is open to any member; everything else is owner-only,
 * and ownership here is positional — the account recorded against the company,
 * not a role or an ability.
 */
class CompanySettingsController extends Controller
{
    public function __construct(private readonly MemberVisibleSettings $memberVisibleSettings) {}

    /**
     * The named preferences of the active company.
     *
     * Options with no row on file are absent from the reply rather than null,
     * so the map that comes back can be shorter than the one asked for — and
     * empty when none of them exist, which serialises as an empty list. The
     * mail transport and module settings are never among them.
     */
    public function show(GetSettingsRequest $request): JsonResponse
    {
        $wanted = (array) $request->input('settings');

        return response()->json(
            $this->memberVisibleSettings->only($wanted, $request->header('company'))
        );
    }

    /**
     * Write a batch of preferences, upserting option by option.
     *
     * One of them is guarded: the trading currency is frozen as soon as the
     * company has anything on its books, and an attempt to move it is refused
     * with a plain 200 carrying `success: false` — no status code, no error
     * bag. The comparison against the stored value is strict, so submitting
     * the current currency as a number when the store holds it as a string
     * counts as a change and trips the guard.
     */
    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $company = Company::query()->find($request->header('company'));

        $this->authorize('manage company', $company);

        $submitted = $request->input('settings');

        if ($this->movesCurrency($submitted, $company) && $company->hasTransactions()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update company currency after transactions are created.',
            ]);
        }

        CompanySetting::setSettings($submitted, $request->header('company'));

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Whether the company has anything on its books yet — the flag the SPA
     * uses to grey out the currency selector before the write is attempted.
     */
    public function checkTransactions(Request $request): JsonResponse
    {
        $company = Company::query()->find($request->header('company'));

        $this->authorize('manage company', $company);

        return response()->json([
            'has_transactions' => $company->hasTransactions(),
        ]);
    }

    /**
     * Hand the active company to one of its members.
     *
     * The target has to be a member already; a stranger is turned away with a
     * 200 carrying `success: false`, in the same shape as the currency guard.
     * On success the owner column moves and the target's roles in this company
     * are replaced by `owner` alone. Nothing is taken away from the outgoing
     * owner beyond the column itself — their role assignments stay, and with
     * them everything those roles allow.
     */
    public function transferOwnership(Request $request, User $user): JsonResponse
    {
        $company = Company::query()->find($request->header('company'));

        $this->authorize('transfer company ownership', $company);

        if (! $user->hasCompany($company->id)) {
            return response()->json([
                'success' => false,
                'message' => 'User does not belong to this company.',
            ]);
        }

        $company->update(['owner_id' => $user->id]);

        BouncerFacade::scope()->to($company->id);
        BouncerFacade::sync($user)->roles(['owner']);

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Whether the submitted batch carries a currency different from the one on
     * file. A batch without a currency key never trips the guard, even when
     * the company is trading.
     */
    private function movesCurrency(mixed $submitted, Company $company): bool
    {
        if (! Arr::exists($submitted, 'currency')) {
            return false;
        }

        return CompanySetting::getSetting('currency', $company->id) !== $submitted['currency'];
    }
}
