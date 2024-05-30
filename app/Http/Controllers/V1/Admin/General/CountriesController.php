<?php

namespace App\Http\Controllers\V1\Admin\General;

use App\Http\Controllers\Controller;
use App\Http\Resources\CountryResource;
use App\Models\Country;
use Illuminate\Http\Request;

class CountriesController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(Request $request)
    {
        $countries = Country::all();

        return CountryResource::collection($countries);
    }
}
