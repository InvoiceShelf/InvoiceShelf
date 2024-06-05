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
            if ($request->has('file_disk_id')) {
                $file_disk = FileDisk::find($request->file_disk_id);
            } else {
                $file_disk = FileDisk::whereSetAsDefault(true)->first();
            }

            if ($file_disk) {
                $file_disk->setConfig();
            }
        }

        return $next($request);
    }
}
