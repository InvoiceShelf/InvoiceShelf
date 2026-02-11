<?php

namespace App\Http\Controllers\V1\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\MailEnvironmentRequest;
use App\Mail\TestMail;
use App\Models\CompanySetting;
use App\Models\Setting;
use App\Services\CompanyMailConfigurationService;
use App\Space\EnvironmentManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mail;

class MailConfigurationController extends Controller
{
    /**
     * The environment manager
     *
     * @var EnvironmentManager
     */
    protected $environmentManager;

    /**
     * The constructor
     */
    public function __construct(EnvironmentManager $environmentManager)
    {
        $this->environmentManager = $environmentManager;
    }

    /**
     * Save the mail environment variables
     *
     * @return JsonResponse
     *
     * @throws AuthorizationException
     */
    public function saveMailEnvironment(MailEnvironmentRequest $request)
    {
        $this->authorize('manage email config');

        $setting = Setting::getSetting('profile_complete');

        // Prepare mail settings for database storage
        $mailSettings = $this->prepareMailSettingsForDatabase($request);

        // Save mail settings to database
        Setting::setSettings($mailSettings);

        if ($setting !== 'COMPLETED') {
            Setting::setSetting('profile_complete', 4);
        }

        return response()->json([
            'success' => 'mail_variables_save_successfully',
        ]);
    }

    /**
     * Prepare mail settings for database storage
     *
     * @return array
     */
    private function prepareMailSettingsForDatabase(MailEnvironmentRequest $request)
    {
        $driver = $request->get('mail_driver');

        // Base settings that are always saved
        $settings = [
            'mail_driver' => $driver,
            'from_name' => $request->get('from_name'),
            'from_mail' => $request->get('from_mail'),
        ];

        // Driver-specific settings
        switch ($driver) {
            case 'smtp':
                $settings = array_merge($settings, [
                    'mail_host' => $request->get('mail_host'),
                    'mail_port' => $request->get('mail_port'),
                    'mail_username' => $request->get('mail_username'),
                    'mail_password' => $request->get('mail_password'),
                    'mail_encryption' => $request->get('mail_encryption', 'none'),
                    'mail_scheme' => $request->get('mail_scheme'),
                    'mail_url' => $request->get('mail_url'),
                    'mail_timeout' => $request->get('mail_timeout'),
                    'mail_local_domain' => $request->get('mail_local_domain'),
                ]);
                break;

            case 'mailgun':
                $settings = array_merge($settings, [
                    'mail_mailgun_domain' => $request->get('mail_mailgun_domain'),
                    'mail_mailgun_secret' => $request->get('mail_mailgun_secret'),
                    'mail_mailgun_endpoint' => $request->get('mail_mailgun_endpoint', 'api.mailgun.net'),
                    'mail_mailgun_scheme' => $request->get('mail_mailgun_scheme', 'https'),
                ]);
                break;

            case 'ses':
                $settings = array_merge($settings, [
                    'mail_ses_key' => $request->get('mail_ses_key'),
                    'mail_ses_secret' => $request->get('mail_ses_secret'),
                    'mail_ses_region' => $request->get('mail_ses_region', 'us-east-1'),
                ]);
                break;

            case 'sendmail':
                $settings = array_merge($settings, [
                    'mail_sendmail_path' => $request->get('mail_sendmail_path', '/usr/sbin/sendmail -bs -i'),
                ]);
                break;
        }

        // Ensure no null values are passed to the database
        return array_map(fn ($value) => $value ?? '', $settings);
    }

    /**
     * Return the mail environment variables
     *
     * @return JsonResponse
     *
     * @throws AuthorizationException
     */
    public function getMailEnvironment()
    {
        $this->authorize('manage email config');

        // Get mail settings from database
        $mailSettings = Setting::getSettings([
            'mail_driver',
            'mail_host',
            'mail_port',
            'mail_username',
            'mail_password',
            'mail_encryption',
            'mail_scheme',
            'mail_url',
            'mail_timeout',
            'mail_local_domain',
            'from_name',
            'from_mail',
            'mail_mailgun_domain',
            'mail_mailgun_secret',
            'mail_mailgun_endpoint',
            'mail_mailgun_scheme',
            'mail_ses_key',
            'mail_ses_secret',
            'mail_ses_region',
            'mail_sendmail_path',
        ]);

        $driver = $mailSettings['mail_driver'] ?? config('mail.default');

        // Base data that's always available
        $MailData = [
            'mail_driver' => $driver,
            'from_name' => $mailSettings['from_name'] ?? config('mail.from.name'),
            'from_mail' => $mailSettings['from_mail'] ?? config('mail.from.address'),
        ];

        // Driver-specific configuration
        switch ($driver) {
            case 'smtp':
                $MailData = array_merge($MailData, [
                    'mail_host' => $mailSettings['mail_host'] ?? '',
                    'mail_port' => $mailSettings['mail_port'] ?? '',
                    'mail_username' => $mailSettings['mail_username'] ?? '',
                    'mail_password' => $mailSettings['mail_password'] ?? '',
                    'mail_encryption' => $mailSettings['mail_encryption'] ?? 'none',
                    'mail_scheme' => $mailSettings['mail_scheme'] ?? '',
                    'mail_url' => $mailSettings['mail_url'] ?? '',
                    'mail_timeout' => $mailSettings['mail_timeout'] ?? '',
                    'mail_local_domain' => $mailSettings['mail_local_domain'] ?? '',
                ]);
                break;

            case 'mailgun':
                $MailData = array_merge($MailData, [
                    'mail_mailgun_domain' => $mailSettings['mail_mailgun_domain'] ?? '',
                    'mail_mailgun_secret' => $mailSettings['mail_mailgun_secret'] ?? '',
                    'mail_mailgun_endpoint' => $mailSettings['mail_mailgun_endpoint'] ?? 'api.mailgun.net',
                    'mail_mailgun_scheme' => $mailSettings['mail_mailgun_scheme'] ?? 'https',
                ]);
                break;

            case 'ses':
                $MailData = array_merge($MailData, [
                    'mail_ses_key' => $mailSettings['mail_ses_key'] ?? '',
                    'mail_ses_secret' => $mailSettings['mail_ses_secret'] ?? '',
                    'mail_ses_region' => $mailSettings['mail_ses_region'] ?? 'us-east-1',
                ]);
                break;

            case 'sendmail':
                $MailData = array_merge($MailData, [
                    'mail_sendmail_path' => $mailSettings['mail_sendmail_path'] ?? '/usr/sbin/sendmail -bs -i',
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
     *
     * @return JsonResponse
     *
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
     *
     * @return JsonResponse
     *
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

    /**
     * Save mail configuration for a specific company
     *
     * @return JsonResponse
     *
     * @throws AuthorizationException
     */
    public function saveCompanyMailConfiguration(MailEnvironmentRequest $request)
    {
        $this->authorize('manage email config');

        $companyId = $request->header('company');

        // Prepare mail settings for database storage
        $mailSettings = $this->prepareMailSettingsForDatabase($request);

        // Save mail settings to company_settings table
        CompanySetting::setSettings($mailSettings, $companyId);

        return response()->json([
            'success' => 'mail_variables_save_successfully',
        ]);
    }

    /**
     * Get mail configuration for a specific company
     *
     * @return JsonResponse
     *
     * @throws AuthorizationException
     */
    public function getCompanyMailConfiguration(Request $request)
    {
        $this->authorize('manage email config');

        $companyId = $request->header('company');

        // Get mail settings from company_settings
        $mailSettings = CompanySetting::getSettings([
            'mail_driver',
            'mail_host',
            'mail_port',
            'mail_username',
            'mail_password',
            'mail_encryption',
            'mail_scheme',
            'mail_url',
            'mail_timeout',
            'mail_local_domain',
            'from_name',
            'from_mail',
            'mail_mailgun_domain',
            'mail_mailgun_secret',
            'mail_mailgun_endpoint',
            'mail_mailgun_scheme',
            'mail_ses_key',
            'mail_ses_secret',
            'mail_ses_region',
            'mail_sendmail_path',
        ], $companyId)->toArray();

        $driver = $mailSettings['mail_driver'] ?? config('mail.default');

        // Base data that's always available
        $MailData = [
            'mail_driver' => $driver,
            'from_name' => $mailSettings['from_name'] ?? config('mail.from.name'),
            'from_mail' => $mailSettings['from_mail'] ?? config('mail.from.address'),
        ];

        // Driver-specific configuration
        switch ($driver) {
            case 'smtp':
                $MailData = array_merge($MailData, [
                    'mail_host' => $mailSettings['mail_host'] ?? '',
                    'mail_port' => $mailSettings['mail_port'] ?? '',
                    'mail_username' => $mailSettings['mail_username'] ?? '',
                    'mail_password' => $mailSettings['mail_password'] ?? '',
                    'mail_encryption' => $mailSettings['mail_encryption'] ?? 'none',
                    'mail_scheme' => $mailSettings['mail_scheme'] ?? '',
                    'mail_url' => $mailSettings['mail_url'] ?? '',
                    'mail_timeout' => $mailSettings['mail_timeout'] ?? '',
                    'mail_local_domain' => $mailSettings['mail_local_domain'] ?? '',
                ]);
                break;

            case 'mailgun':
                $MailData = array_merge($MailData, [
                    'mail_mailgun_domain' => $mailSettings['mail_mailgun_domain'] ?? '',
                    'mail_mailgun_secret' => $mailSettings['mail_mailgun_secret'] ?? '',
                    'mail_mailgun_endpoint' => $mailSettings['mail_mailgun_endpoint'] ?? 'api.mailgun.net',
                    'mail_mailgun_scheme' => $mailSettings['mail_mailgun_scheme'] ?? 'https',
                ]);
                break;

            case 'ses':
                $MailData = array_merge($MailData, [
                    'mail_ses_key' => $mailSettings['mail_ses_key'] ?? '',
                    'mail_ses_secret' => $mailSettings['mail_ses_secret'] ?? '',
                    'mail_ses_region' => $mailSettings['mail_ses_region'] ?? 'us-east-1',
                ]);
                break;

            case 'sendmail':
                $MailData = array_merge($MailData, [
                    'mail_sendmail_path' => $mailSettings['mail_sendmail_path'] ?? '/usr/sbin/sendmail -bs -i',
                ]);
                break;

            default:
                // For unknown drivers, return minimal configuration
                break;
        }

        return response()->json($MailData);
    }
}
