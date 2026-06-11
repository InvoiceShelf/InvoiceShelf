<?php

namespace App\Http\Controllers\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\EstimateResource;
use App\Mail\EstimateViewedMail;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\EmailLog;
use App\Models\Estimate;
use Illuminate\Http\Request;

class EstimatePdfController extends Controller
{
    public function getPdf(EmailLog $emailLog, Request $request)
    {
        $estimate = $emailLog->mailable;
        abort_unless($estimate instanceof Estimate, 404);
        abort_if($emailLog->isExpired(), 403, 'Link Expired.');

        if ($estimate->status == Estimate::STATUS_SENT || $estimate->status == Estimate::STATUS_DRAFT) {
            $estimate->status = Estimate::STATUS_VIEWED;
            $estimate->save();
            $notifyEstimateViewed = CompanySetting::getSetting(
                'notify_estimate_viewed',
                $estimate->company_id
            );

            if ($notifyEstimateViewed == 'YES') {
                $data['estimate'] = Estimate::findOrFail($estimate->id)->toArray();
                $data['user'] = Customer::find($estimate->customer_id)->toArray();
                $notificationEmail = CompanySetting::getSetting(
                    'notification_email',
                    $estimate->company_id
                );

                \Mail::to($notificationEmail)->send(new EstimateViewedMail($data));
            }
        }

        return $estimate->getGeneratedPDFOrStream('estimate');
    }

    public function getEstimate(EmailLog $emailLog)
    {
        $estimate = $emailLog->mailable;
        abort_unless($estimate instanceof Estimate, 404);
        abort_if($emailLog->isExpired(), 403, 'Link Expired.');

        return new EstimateResource($estimate);
    }
}
