<?php

namespace App\Http\Controllers\V1\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\PDFConfigurationRequest;
use App\Models\Setting;
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

        // Get PDF settings from database
        $pdfSettings = Setting::getSettings([
            'pdf_driver',
            'gotenberg_host',
            'gotenberg_papersize',
            'gotenberg_margins',
        ]);

        $config = [
            'pdf_driver' => $pdfSettings['pdf_driver'] ?? config('pdf.driver'),
            'gotenberg_host' => $pdfSettings['gotenberg_host'] ?? config('pdf.connections.gotenberg.host'),
            'gotenberg_margins' => $pdfSettings['gotenberg_margins'] ?? config('pdf.connections.gotenberg.margins'),
            'gotenberg_papersize' => $pdfSettings['gotenberg_papersize'] ?? config('pdf.connections.gotenberg.papersize'),
        ];

        return response()->json($config);
    }

    public function saveEnvironment(PDFConfigurationRequest $request)
    {
        $this->authorize('manage pdf config');

        // Prepare PDF settings for database storage
        $pdfSettings = $this->preparePDFSettingsForDatabase($request);

        // Save PDF settings to database
        Setting::setSettings($pdfSettings);

        return response()->json([
            'success' => 'pdf_variables_save_successfully',
        ]);
    }

    /**
     * Prepare PDF settings for database storage
     *
     * @return array
     */
    private function preparePDFSettingsForDatabase(PDFConfigurationRequest $request)
    {
        $driver = $request->get('pdf_driver');

        // Base settings that are always saved
        $settings = [
            'pdf_driver' => $driver,
        ];

        // Driver-specific settings
        switch ($driver) {
            case 'gotenberg':
                $settings = array_merge($settings, [
                    'gotenberg_host' => $request->get('gotenberg_host'),
                    'gotenberg_papersize' => $request->get('gotenberg_papersize'),
                    'gotenberg_margins' => $request->get('gotenberg_margins'),
                ]);
                break;

            case 'dompdf':
                // dompdf doesn't have additional configuration in the current setup
                break;
        }

        return $settings;
    }
}
