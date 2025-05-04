<?php

namespace App\Http\Controllers\V1\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\PDFConfigurationRequest;
use App\Space\EnvironmentManager;

class PDFConfigurationController extends Controller
{
    /**
     * @var EnvironmentManager
     */
    protected $environmentManager;

    public function __construct(EnvironmentManager $environmentManager)
    {
        $this->environmentManager = $environmentManager;
    }

    public function getDrivers()
    {
        $this->authorize('manage pdf config');

        $drivers = [
            'dompdf',
            'gotenberg',
        ];

        return response()->json($drivers);
    }

    public function getEnvironment()
    {
        $this->authorize('manage pdf config');

        $config = [
            'pdf_driver' => config('pdf.driver'),
            'gotenberg_host' => config('pdf.gotenberg.host'),
            'gotenberg_margins' => config('pdf.gotenberg.margins'),
            'gotenberg_papersize' => config('pdf.gotenberg.papersize'),
        ];

        return response()->json($config);
    }

    public function saveEnvironment(PDFConfigurationRequest $request)
    {
        $this->authorize('manage pdf config');
        $results = $this->environmentManager->savePDFVariables($request);

        return response()->json($results);
    }
}
