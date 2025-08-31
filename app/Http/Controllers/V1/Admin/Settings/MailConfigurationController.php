<?php

namespace App\Http\Controllers\V1\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\MailEnvironmentRequest;
use App\Mail\TestMail;
use App\Models\Setting;
use App\Space\EnvironmentManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mail;

class MailConfigurationController extends Controller
{
    /**
     * The environment manager
     * @var EnvironmentManager
     */
    protected $environmentManager;

    /**
     * The constructor
     * @param  EnvironmentManager  $environmentManager
     */
    public function __construct(EnvironmentManager $environmentManager)
    {
        $this->environmentManager = $environmentManager;
    }

    /**
     * Save the mail environment variables
     * @param  MailEnvironmentRequest  $request
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function saveMailEnvironment(MailEnvironmentRequest $request)
    {
        $this->authorize('manage email config');

        $setting = Setting::getSetting('profile_complete');
        $results = $this->environmentManager->saveMailVariables($request);

        if ($setting !== 'COMPLETED') {
            Setting::setSetting('profile_complete', 4);
        }

        return response()->json($results);
    }

    /**
     * Return the mail environment variables
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function getMailEnvironment()
    {
        $this->authorize('manage email config');

        $driver = config('mail.default');

        // Base data that's always available
        $MailData = [
            'mail_driver' => $driver,
            'from_name' => config('mail.from.name'),
            'from_mail' => config('mail.from.address'),
        ];

        // Driver-specific configuration
        switch ($driver) {
            case 'smtp':
                $MailData = array_merge($MailData, [
                    'mail_host' => config('mail.mailers.smtp.host'),
                    'mail_port' => config('mail.mailers.smtp.port'),
                    'mail_username' => config('mail.mailers.smtp.username'),
                    'mail_password' => config('mail.mailers.smtp.password'),
                    'mail_encryption' => config('mail.mailers.smtp.scheme') ?? 'none',
                    'mail_scheme' => config('mail.mailers.smtp.scheme'),
                    'mail_url' => config('mail.mailers.smtp.url'),
                    'mail_timeout' => config('mail.mailers.smtp.timeout'),
                    'mail_local_domain' => config('mail.mailers.smtp.local_domain'),
                ]);
                break;

            case 'mailgun':
                $MailData = array_merge($MailData, [
                    'mail_mailgun_domain' => config('mail.mailers.mailgun.domain'),
                    'mail_mailgun_secret' => config('mail.mailers.mailgun.secret'),
                    'mail_mailgun_endpoint' => config('mail.mailers.mailgun.endpoint'),
                    'mail_mailgun_scheme' => config('mail.mailers.mailgun.scheme'),
                ]);
                break;

            case 'ses':
                $MailData = array_merge($MailData, [
                    'mail_ses_key' => config('services.ses.key'),
                    'mail_ses_secret' => config('services.ses.secret'),
                    'mail_ses_region' => config('services.ses.region'),
                ]);
                break;

            case 'sendmail':
                $MailData = array_merge($MailData, [
                    'mail_sendmail_path' => config('mail.mailers.sendmail.path'),
                ]);
                break;

            default:
                // For unknown drivers, return minimal configuration
                break;
        }

        return response()->json($MailData);
    }

    /**
     * Return the available mail drivers
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function getMailDrivers()
    {
        $this->authorize('manage email config');

        $drivers = [
            'smtp',
            'mail',
            'sendmail',
            'mailgun',
            'ses',
        ];

        return response()->json($drivers);
    }

    /**
     * Test the email configuration
     * @param  Request  $request
     *
     * @return JsonResponse
     * @throws AuthorizationException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function testEmailConfig(Request $request)
    {
        $this->authorize('manage email config');

        $this->validate($request, [
            'to' => 'required|email',
            'subject' => 'required',
            'message' => 'required',
        ]);

        Mail::to($request->to)->send(new TestMail($request->subject, $request->message));

        return response()->json([
            'success' => true,
        ]);
    }
}
