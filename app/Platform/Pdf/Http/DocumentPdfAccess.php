<?php

namespace App\Platform\Pdf\Http;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Silber\Bouncer\BouncerFacade;

/**
 * Who may open a document's PDF by its `unique_hash`: the customer it was
 * issued to, or a member of its company whose role lets them view it.
 *
 * PdfMiddleware only checks that someone is signed in, on any of three
 * guards, so without this any customer or any user of any company could open
 * every document whose hash they had or could guess. A refusal answers 404,
 * so the reply does not confirm that the document exists.
 */
final class DocumentPdfAccess
{
    /**
     * @param  Model  $document  An invoice, estimate or payment: it carries `company_id` and `customer_id`.
     */
    public static function authorize(Model $document): void
    {
        abort_unless(self::allows($document), 404);
    }

    private static function allows(Model $document): bool
    {
        $customer = Auth::guard('customer')->user();

        if ($customer !== null && (int) $customer->getKey() === (int) $document->customer_id) {
            return true;
        }

        foreach (['web', 'sanctum'] as $guard) {
            $user = Auth::guard($guard)->user();

            if ($user === null || ! $user->hasCompany($document->company_id)) {
                continue;
            }

            // The policies ask Bouncer about the default guard's user, in the
            // company Bouncer is scoped to; neither is set on these routes.
            Auth::shouldUse($guard);
            BouncerFacade::scope()->to($document->company_id);

            if (Gate::allows('view', $document)) {
                return true;
            }
        }

        return false;
    }
}
