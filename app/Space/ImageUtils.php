<?php

namespace App\Space;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ImageUtils
{
    /**
     * Convert local path to Base64 encoded data source
     *
     * @return string
     */
    public static function toBase64Src($path)
    {
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            $response = Http::timeout(10)->get($path);
            $response->throw();

            $mimeType = Str::before($response->header('Content-Type') ?? 'application/octet-stream', ';');

            return sprintf('data:%s;base64,%s', $mimeType, base64_encode($response->body()));
        }

        return sprintf('data:%s;base64,%s', File::mimeType($path), base64_encode(File::get($path)));
    }
}
