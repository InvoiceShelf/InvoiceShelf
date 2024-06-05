<?php

namespace App\Http\Controllers\V1\Installation;

use App\Http\Controllers\Controller;
use App\Space\RequirementsChecker;
use Illuminate\Http\JsonResponse;

class RequirementsController extends Controller
{
    /**
     * @var RequirementsChecker
     */
    protected $requirements;

    public function __construct(RequirementsChecker $checker)
    {
        $this->requirements = $checker;
    }

    /**
     * Display the requirements page.
     *
     * @return JsonResponse
     */
    public function requirements()
    {
        $phpSupportInfo = $this->requirements->checkPHPVersion(
            config('installer.core.minPhpVersion')
        );

        $requirements = $this->requirements->check(
            config('installer.requirements')
        );

        return response()->json([
            'phpSupportInfo' => $phpSupportInfo,
            'requirements' => $requirements,
        ]);
    }
}
