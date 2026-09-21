<?php

namespace App\Platform\Operations\Http\Webhooks;

use App\Platform\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * Webhook that lets an external scheduler drive Laravel's own scheduler on
 * installs that cannot register a system cron entry.
 */
class CronJobController extends Controller
{
    /**
     * How long one pass owns the scheduler, in seconds.
     *
     * Laravel decides what is due from the current minute, so running the
     * scheduler twice inside one minute runs the same due tasks twice. The
     * callers of this endpoint are external and cannot be relied on to keep
     * to one call a minute.
     */
    private const LOCK_SECONDS = 55;

    /**
     * Run the due scheduled tasks. The shared-token check has already been
     * made by the route middleware, so nothing is read off the request.
     *
     * A second caller inside the same window is answered successfully without
     * running anything: from its point of view the schedule has been driven
     * and there is nothing to retry.
     */
    public function __invoke(Request $request): JsonResponse
    {
        if (! Cache::lock('cron-webhook', self::LOCK_SECONDS)->get()) {
            return response()->json(['success' => true, 'ran' => false]);
        }

        Artisan::call('schedule:run');

        return response()->json(['success' => true, 'ran' => true]);
    }
}
