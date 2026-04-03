<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class LanguagesController extends Controller
{
    /**
     * Display the languages page.
     *
     * @return JsonResponse
     */
    public function languages()
    {
        return response()->json([
            'languages' => config('invoiceshelf.languages'),
        ]);
    }
}
