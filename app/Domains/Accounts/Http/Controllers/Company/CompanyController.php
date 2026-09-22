<?php

namespace App\Domains\Accounts\Http\Controllers\Company;

use App\Domains\Accounts\Contracts\CompanyAddressWriter;
use App\Domains\Accounts\Contracts\CompanyLogoManager;
use App\Domains\Accounts\Http\Requests\CompanyLogoRequest;
use App\Domains\Accounts\Http\Requests\CompanyRequest;
use App\Domains\Accounts\Http\Resources\CompanyResource;
use App\Domains\Accounts\Models\Company;
use App\Domains\Metadata\Contracts\CustomFieldValueWriter;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;

/**
 * The profile of the company the request header points at.
 *
 * Neither endpoint takes a routed model: both resolve the company from the
 * `company` header and then ask the same gate whether the caller is the
 * account recorded against it as owner. Ability grants buy nothing here, and
 * neither does the platform-administrator flag.
 */
class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyAddressWriter $companyAddressWriter,
        private readonly CompanyLogoManager $companyLogoManager,
        private readonly CustomFieldValueWriter $customFieldValueWriter,
    ) {}

    /**
     * Rewrite the company record and upsert its postal address.
     *
     * The address block is read from the raw payload rather than the validated
     * set: only its country carries a rule, so the validated set would shrink
     * the address down to that single column. Kept as it stands.
     *
     * A payload carrying no address block at all still reaches the writer — as
     * an empty array, which is what the cast of a missing key produces.
     */
    public function updateCompany(CompanyRequest $request)
    {
        $company = $this->companyFromHeader($request);

        $this->authorize('manage company', $company);

        $company->update($request->getCompanyPayload());

        $address = (array) $request->input('address');

        $this->companyAddressWriter->upsert($company, $address);

        if ($customFields = $request->input('customFields')) {
            $this->customFieldValueWriter->update($company, $customFields, $request->header('company'));
        }

        return new CompanyResource($company->refresh());
    }

    /**
     * Replace or drop the company logo.
     *
     * Two independent switches, in this order: the removal flag wipes whatever
     * is on file, and a submitted image is then stored — so a payload carrying
     * both ends up with the new image. The image arrives as a JSON envelope
     * holding a file name and a data URI, already checked by the form request,
     * and an envelope that decodes to nothing is simply ignored.
     */
    public function uploadCompanyLogo(CompanyLogoRequest $request)
    {
        $company = $this->companyFromHeader($request);

        $this->authorize('manage company', $company);

        if ($this->removalRequested($request)) {
            $this->companyLogoManager->clear($company);
        }

        $envelope = json_decode((string) $request->input('company_logo'));

        if ($envelope) {
            $this->companyLogoManager->replaceBase64($company, $envelope->data, $envelope->name);
        }

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * The company named by the request header, or null when the header names
     * nothing on file — the gate is then asked about a company that is not
     * there, exactly as before.
     */
    private function companyFromHeader(Request $request): ?Company
    {
        return Company::query()->find($request->header('company'));
    }

    /**
     * Whether the caller asked for the current logo to be dropped.
     *
     * Present-and-not-null, then cast to a boolean: `"0"` and the empty string
     * read as no, but the string `"false"` reads as yes. Kept as it stands.
     */
    private function removalRequested(Request $request): bool
    {
        $flag = $request->input('is_company_logo_removed');

        return $flag !== null && (bool) $flag;
    }
}
