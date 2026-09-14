<?php

namespace App\Domains\Accounts\Http\Controllers\Company;

use App\Domains\Accounts\Contracts\AbilityCatalog;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;

/**
 * Publishes the catalog of abilities a role can be granted.
 *
 * The catalog is the same list for every company and every caller, which is
 * why nothing here is scoped or filtered. The role editor draws its checkboxes
 * from it, so the entries travel exactly as the catalogue declares them --
 * same order, subjects and dependencies included. What the list contains can
 * still change between requests: an enabled module contributes its own
 * abilities to it.
 */
class AbilitiesController extends Controller
{
    /**
     * The whole catalog, host configuration first and module abilities after.
     */
    public function __invoke(Request $request, AbilityCatalog $catalog)
    {
        return response()->json([
            'abilities' => $catalog->all(),
        ]);
    }
}
