<?php

// Implementation taken from nova-backup-tool - https://github.com/spatie/nova-backup-tool/

namespace App\Http\Controllers\V1\Admin\Backup;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ApiController extends Controller
{
    public function respondSuccess(): JsonResponse
    {
        return response()->json([
            'success' => true,
        ]);
    }
}
