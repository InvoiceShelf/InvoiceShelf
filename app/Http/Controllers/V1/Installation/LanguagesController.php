<?php

namespace InvoiceShelf\Http\Controllers\V1\Installation;

use Illuminate\Http\JsonResponse;
use InvoiceShelf\Http\Controllers\Controller;

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
