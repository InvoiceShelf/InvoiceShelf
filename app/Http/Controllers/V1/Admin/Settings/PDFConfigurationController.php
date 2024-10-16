<?php

namespace App\Http\Controllers\V1\Admin\Settings;

use App\Http\Controllers\Controller;

class PDFConfigurationController extends Controller
{

    public function getDrivers()
    {
        $this->authorize('manage email config');

        $drivers = [
            'dompdf',
            'gotenberg',
        ];

        return response()->json($drivers);
    }

    public function getEnvironment()
    {
        $this->authorize('manage email config');

        $config = [
            'pdf_driver' => config('pdf.driver'),
            'gotenberg_host' => config('pdf.gotenberg.host'),
            'gotenberg_margins' => config('pdf.gotenberg.margins'),
            'gotenberg_papersize' => config('pdf.gotenberg.papersize'),
        ];

        return response()->json($config);
    }

    public function saveEnvironment() {

    }
}
