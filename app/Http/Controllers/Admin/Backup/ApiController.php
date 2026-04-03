<?php

// Implementation taken from nova-backup-tool - https://github.com/spatie/nova-backup-tool/

namespace App\Http\Controllers\Admin\Backup;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ApiController extends Controller
{
    /**
     * Resoibd wutg syccess
     */
    public function respondSuccess(): JsonResponse
    {
        return response()->json([
            'success' => true,
        ]);
    }
}
