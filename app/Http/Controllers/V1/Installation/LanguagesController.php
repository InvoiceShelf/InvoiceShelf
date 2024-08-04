<?php

namespace App\Http\Controllers\V1\Installation;

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
