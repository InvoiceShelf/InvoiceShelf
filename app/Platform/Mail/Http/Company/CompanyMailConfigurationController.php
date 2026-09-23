<?php

namespace App\Platform\Mail\Http\Company;

use App\Platform\Http\Controller;
use App\Platform\Mail\Application\MailConfigurationService;
use App\Platform\Mail\Http\Requests\CompanyMailConfigurationRequest;
use App\Platform\Mail\Mailables\TestMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class CompanyMailConfigurationController extends Controller
{
    public function __construct(private readonly MailConfigurationService $mailConfigurationService) {}

    public function getDefaultConfig(Request $request): JsonResponse
    {
        $this->authorize('owner only');

        return response()->json($this->mailConfigurationService->getDefaultConfig());
    }

    public function getMailConfig(Request $request): JsonResponse
    {
        $this->authorize('owner only');

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

        $this->mailConfigurationService->applyCompanyConfig($request->header('company'));

        Mail::to($request->to)->send(new TestMail($request->subject, $request->message));

        return response()->json(['success' => true]);
    }
}
