<?php

namespace App\Domains\Contacts\Http\Controllers;

use App\Domains\Contacts\Http\Resources\CountryResource;
use App\Domains\Contacts\Models\Country;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The country reference list: every row, unfiltered and unpaginated.
 *
 * Mounted twice — once on the company API, once inside the customer portal —
 * which is why the request is accepted and never read. Neither caller narrows
 * the list; both want the whole thing to fill a picker.
 *
 * Sorted by name with accents folded, so "Åland Islands" sits under A and the
 * countries added after the original list (ids from 247) appear in place
 * rather than after Zimbabwe.
 */
class CountriesController extends Controller
{
    public function __invoke(Request $request)
    {
        $countries = Country::query()
            ->get()
            ->sortBy(fn (Country $country): string => Str::lower(Str::ascii($country->name)))
            ->values();

        return CountryResource::collection($countries);
    }
}
