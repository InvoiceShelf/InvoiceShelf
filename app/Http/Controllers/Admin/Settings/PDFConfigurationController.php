<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\PDFConfigurationRequest;
use App\Models\Setting;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class PDFConfigurationController extends Controller
{
    /**
     * Driver-neutral page geometry. Each maps onto `pdf.page.*` in config, and is
     * read and written for every driver rather than being nested under one.
     */
    private const PAGE_SETTINGS = [
        'pdf_paper_width',
        'pdf_paper_height',
        'pdf_orientation',
        'pdf_margin_top',
        'pdf_margin_right',
        'pdf_margin_bottom',
        'pdf_margin_left',
    ];

    /**
     * Returns the available drivers
     *
     * @throws AuthorizationException
     */
    public function getDrivers(): JsonResponse
    {
        $this->authorize('manage pdf config');

        $drivers = [
            'dompdf',
            'gotenberg',
        ];

        return response()->json($drivers);
    }

    /**
     * Return the PDF settings
     *
     * @throws AuthorizationException
     */
    public function getEnvironment(): JsonResponse
    {
        $this->authorize('manage pdf config');

        $pdfSettings = Setting::getSettings(array_merge(
            ['pdf_driver', 'gotenberg_host'],
            self::PAGE_SETTINGS,
        ));

        $config = [
            'pdf_driver' => $pdfSettings['pdf_driver'] ?? config('pdf.driver'),
            'gotenberg_host' => $pdfSettings['gotenberg_host'] ?? config('pdf.connections.gotenberg.host'),
        ];

        // Page geometry applies to whichever driver is selected, so it is always
        // returned rather than nested under a driver branch.
        foreach (self::PAGE_SETTINGS as $setting) {
            $config[$setting] = $pdfSettings[$setting] ?? config(self::configKeyFor($setting));
        }

        return response()->json($config);
    }

    /**
     * Saves the settings
     *
     * @throws AuthorizationException
     */
    public function saveEnvironment(PDFConfigurationRequest $request): JsonResponse
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
     */
    private function preparePDFSettingsForDatabase(PDFConfigurationRequest $request): array
    {
        $driver = $request->get('pdf_driver');

        $settings = ['pdf_driver' => $driver];

        // Page geometry is saved for every driver: switching between them should
        // not lose the paper size, which is what happened while it was a
        // Gotenberg-only setting.
        foreach (self::PAGE_SETTINGS as $setting) {
            $settings[$setting] = $request->get($setting);
        }

        if ($driver === 'gotenberg') {
            $settings['gotenberg_host'] = $request->get('gotenberg_host');
        }

        return $settings;
    }

    /**
     * Maps a settings key onto its config counterpart, e.g.
     * `pdf_margin_top` -> `pdf.page.margin_top`.
     */
    private static function configKeyFor(string $setting): string
    {
        return 'pdf.page.'.substr($setting, strlen('pdf_'));
    }
}
