<?php

namespace App\Http\Controllers\V1\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Mail\TestMail;
use App\Models\CompanySetting;
use App\Services\CompanyMailConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mail;

class CompanyMailConfigurationController extends Controller
{
    public function getDefaultConfig(Request $request): JsonResponse
    {
        $mailConfig = [
            'from_name' => config('mail.from.name'),
            'from_mail' => config('mail.from.address'),
        ];

        return response()->json($mailConfig);
    }

    public function getMailConfig(Request $request): JsonResponse
    {
        $companyId = $request->header('company');

        $useCustom = CompanySetting::getSetting('use_custom_mail_config', $companyId) ?? 'NO';
        $driver = CompanySetting::getSetting('company_mail_driver', $companyId) ?? '';

        $data = [
            'use_custom_mail_config' => $useCustom,
            'mail_driver' => $driver,
            'from_name' => CompanySetting::getSetting('company_from_name', $companyId) ?? '',
            'from_mail' => CompanySetting::getSetting('company_from_mail', $companyId) ?? '',
        ];

        switch ($driver) {
            case 'smtp':
                $data = array_merge($data, [
                    'mail_host' => CompanySetting::getSetting('company_mail_host', $companyId) ?? '',
                    'mail_port' => CompanySetting::getSetting('company_mail_port', $companyId) ?? '',
                    'mail_username' => CompanySetting::getSetting('company_mail_username', $companyId) ?? '',
                    'mail_password' => CompanySetting::getSetting('company_mail_password', $companyId) ?? '',
                    'mail_encryption' => CompanySetting::getSetting('company_mail_encryption', $companyId) ?? 'none',
                    'mail_scheme' => CompanySetting::getSetting('company_mail_scheme', $companyId) ?? '',
                    'mail_url' => CompanySetting::getSetting('company_mail_url', $companyId) ?? '',
                    'mail_timeout' => CompanySetting::getSetting('company_mail_timeout', $companyId) ?? '',
                    'mail_local_domain' => CompanySetting::getSetting('company_mail_local_domain', $companyId) ?? '',
                ]);
                break;

            case 'mailgun':
                $data = array_merge($data, [
                    'mail_mailgun_domain' => CompanySetting::getSetting('company_mail_mailgun_domain', $companyId) ?? '',
                    'mail_mailgun_secret' => CompanySetting::getSetting('company_mail_mailgun_secret', $companyId) ?? '',
                    'mail_mailgun_endpoint' => CompanySetting::getSetting('company_mail_mailgun_endpoint', $companyId) ?? 'api.mailgun.net',
                    'mail_mailgun_scheme' => CompanySetting::getSetting('company_mail_mailgun_scheme', $companyId) ?? 'https',
                ]);
                break;

            case 'ses':
                $data = array_merge($data, [
                    'mail_ses_key' => CompanySetting::getSetting('company_mail_ses_key', $companyId) ?? '',
                    'mail_ses_secret' => CompanySetting::getSetting('company_mail_ses_secret', $companyId) ?? '',
                    'mail_ses_region' => CompanySetting::getSetting('company_mail_ses_region', $companyId) ?? 'us-east-1',
                ]);
                break;

            case 'sendmail':
                $data = array_merge($data, [
                    'mail_sendmail_path' => CompanySetting::getSetting('company_mail_sendmail_path', $companyId) ?? '/usr/sbin/sendmail -bs -i',
                ]);
                break;
        }

        return response()->json($data);
    }

    public function saveMailConfig(Request $request): JsonResponse
    {
        $this->authorize('owner only');

        $companyId = $request->header('company');
        $driver = $request->get('mail_driver', '');

        $settings = [
            'use_custom_mail_config' => $request->get('use_custom_mail_config', 'NO'),
            'company_mail_driver' => $driver,
            'company_from_name' => $request->get('from_name', ''),
            'company_from_mail' => $request->get('from_mail', ''),
        ];

        switch ($driver) {
            case 'smtp':
                $settings = array_merge($settings, [
                    'company_mail_host' => $request->get('mail_host', ''),
                    'company_mail_port' => $request->get('mail_port', ''),
                    'company_mail_username' => $request->get('mail_username', ''),
                    'company_mail_password' => $request->get('mail_password', ''),
                    'company_mail_encryption' => $request->get('mail_encryption', 'none'),
                    'company_mail_scheme' => $request->get('mail_scheme', ''),
                    'company_mail_url' => $request->get('mail_url', ''),
                    'company_mail_timeout' => $request->get('mail_timeout', ''),
                    'company_mail_local_domain' => $request->get('mail_local_domain', ''),
                ]);
                break;

            case 'mailgun':
                $settings = array_merge($settings, [
                    'company_mail_mailgun_domain' => $request->get('mail_mailgun_domain', ''),
                    'company_mail_mailgun_secret' => $request->get('mail_mailgun_secret', ''),
                    'company_mail_mailgun_endpoint' => $request->get('mail_mailgun_endpoint', 'api.mailgun.net'),
                    'company_mail_mailgun_scheme' => $request->get('mail_mailgun_scheme', 'https'),
                ]);
                break;

            case 'ses':
                $settings = array_merge($settings, [
                    'company_mail_ses_key' => $request->get('mail_ses_key', ''),
                    'company_mail_ses_secret' => $request->get('mail_ses_secret', ''),
                    'company_mail_ses_region' => $request->get('mail_ses_region', 'us-east-1'),
                ]);
                break;

            case 'sendmail':
                $settings = array_merge($settings, [
                    'company_mail_sendmail_path' => $request->get('mail_sendmail_path', '/usr/sbin/sendmail -bs -i'),
                ]);
                break;
        }

        CompanySetting::setSettings($settings, $companyId);

        return response()->json(['success' => true]);
    }

    public function testMailConfig(Request $request): JsonResponse
    {
        $this->authorize('owner only');

        $this->validate($request, [
            'to' => 'required|email',
            'subject' => 'required',
            'message' => 'required',
        ]);

        CompanyMailConfigService::apply($request->header('company'));

        Mail::to($request->to)->send(new TestMail($request->subject, $request->message));

        return response()->json(['success' => true]);
    }
}
