<?php

namespace App\Domains\Accounts\Http\Controllers\Admin;

use App\Domains\Accounts\Contracts\AbilityCatalog;
use App\Platform\Http\Controller;
use Illuminate\Http\JsonResponse;

/**
 * The ability catalogue for the super administrator's preset editor, the same
 * list a company owner's role editor reads, without needing a company.
 */
class AbilitiesController extends Controller
{
    public function __invoke(AbilityCatalog $catalog): JsonResponse
    {
        return response()->json(['abilities' => $catalog->all()]);
    }
}
