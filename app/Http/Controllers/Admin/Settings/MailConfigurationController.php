<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\MailEnvironmentRequest;
use App\Mail\TestMail;
use App\Models\Setting;
use App\Services\MailConfigurationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class MailConfigurationController extends Controller
{
    public function __construct(private readonly MailConfigurationService $mailConfigurationService) {}

    /**
     * Save the mail environment variables
     *
     *
     *
     * @throws AuthorizationException
     */
    public function saveMailEnvironment(MailEnvironmentRequest $request): JsonResponse
    {
        $this->authorize('manage email config');

        $setting = Setting::getSetting('profile_complete');

        $this->mailConfigurationService->saveGlobalConfig($request->validated());

        if ($setting !== 'COMPLETED') {
            Setting::setSetting('profile_complete', 4);
        }

        return response()->json([
            'success' => 'mail_variables_save_successfully',
        ]);
    }

    /**
     * Return the mail environment variables
     *
     *
     * @throws AuthorizationException
     */
    public function getMailEnvironment(): JsonResponse
    {
        $this->authorize('manage email config');

        return response()->json($this->mailConfigurationService->getGlobalConfig());
    }

    /**
     * Return the available mail drivers
     *
     *
     * @throws AuthorizationException
     */
    public function getMailDrivers(): JsonResponse
    {
        $this->authorize('manage email config');

        return response()->json($this->mailConfigurationService->getAvailableDrivers());
    }

    /**
     * Test the email configuration
     *
     *
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function testEmailConfig(Request $request): JsonResponse
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
