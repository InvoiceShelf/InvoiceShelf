<?php

namespace App\Http\Controllers\Company\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyMailConfigurationRequest;
use App\Mail\TestMail;
use App\Services\CompanyMailConfigService;
use App\Services\MailConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class CompanyMailConfigurationController extends Controller
{
    public function __construct(private readonly MailConfigurationService $mailConfigurationService) {}

    public function getDefaultConfig(Request $request): JsonResponse
    {
        return response()->json($this->mailConfigurationService->getDefaultConfig());
    }

    public function getMailConfig(Request $request): JsonResponse
    {
        return response()->json(
            $this->mailConfigurationService->getCompanyConfig($request->header('company'))
        );
    }

    public function saveMailConfig(CompanyMailConfigurationRequest $request): JsonResponse
    {
        $this->authorize('owner only');

        $this->mailConfigurationService->saveCompanyConfig(
            $request->header('company'),
            $request->validated()
        );

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
