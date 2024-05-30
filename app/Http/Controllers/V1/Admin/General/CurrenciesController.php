<?php

namespace App\Http\Controllers\V1\Admin\General;

use App\Http\Controllers\Controller;
use App\Http\Resources\CurrencyResource;
use App\Models\Currency;
use Illuminate\Http\Request;

class CurrenciesController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        $currencies = Currency::latest()->get();

        return CurrencyResource::collection($currencies);
    }
}
