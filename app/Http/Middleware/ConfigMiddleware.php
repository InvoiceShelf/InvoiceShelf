<?php

namespace App\Http\Middleware;

use App\Models\FileDisk;
use App\Space\InstallUtils;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfigMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (InstallUtils::isDbCreated()) {
            // Only handle dynamic file disk switching when file_disk_id is provided
            if ($request->has('file_disk_id')) {
                $file_disk = FileDisk::find($request->file_disk_id);

                if ($file_disk) {
                    $file_disk->setConfig();
                }
            }
            // Default file disk is now handled by AppConfigProvider during boot
        }

        return $next($request);
    }
}
