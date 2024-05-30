<?php

namespace InvoiceShelf\Http\Middleware;

use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;
use Closure;
use InvoiceShelf\Models\FileDisk;
use InvoiceShelf\Space\InstallUtils;

class ConfigMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
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
