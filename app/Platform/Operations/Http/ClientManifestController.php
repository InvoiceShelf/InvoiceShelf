<?php

namespace App\Platform\Operations\Http;

use App\Platform\Http\Controller;
use App\Platform\Operations\Application\ClientManifestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What a thin client reads instead of the Blade shell.
 *
 * Unauthenticated like the version probe beside it: a client asks for this
 * before it has a token, to decide whether it can talk to this server at all
 * and which module assets to load.
 */
class ClientManifestController extends Controller
{
    /**
     * @return JsonResponse
     */
    public function __invoke(Request $request, ClientManifestService $manifest)
    {
        // Short enough that a rebuilt module asset is picked up on the next
        // cold start, long enough to survive a boot sequence's own retries.
        return response()->json($manifest->build())
            ->header('Cache-Control', 'max-age=60');
    }
}
