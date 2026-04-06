<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FontService;
use Illuminate\Http\JsonResponse;

class FontController extends Controller
{
    public function __construct(
        private readonly FontService $fontService,
    ) {}

    public function status(): JsonResponse
    {
        $this->authorize('manage settings');

        return response()->json([
            'packages' => $this->fontService->getPackageStatuses(),
        ]);
    }

    public function install(string $package): JsonResponse
    {
        $this->authorize('manage settings');

        if (! isset(FontService::FONT_PACKAGES[$package])) {
            return response()->json(['error' => 'Unknown font package'], 404);
        }

        $pkg = FontService::FONT_PACKAGES[$package];

        if ($this->fontService->isInstalled($pkg)) {
            return response()->json(['success' => true, 'message' => 'Already installed']);
        }

        try {
            $this->fontService->downloadPackage($pkg);

            return response()->json([
                'success' => true,
                'installed' => true,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
