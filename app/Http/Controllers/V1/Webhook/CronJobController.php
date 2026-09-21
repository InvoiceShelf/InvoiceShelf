<?php

namespace App\Http\Controllers\V1\Webhook;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class CronJobController extends Controller
{
    /**
     * How long one pass owns the scheduler, in seconds.
     *
     * Laravel's scheduler decides what is due from the current minute, so
     * running it twice inside the same minute runs the same due tasks twice,
     * and generates the same recurring invoice twice. External schedulers are
     * not always precise, and some callers poll far more often than they are
     * meant to, so a caller cannot be trusted to keep to one call a minute.
     */
    private const LOCK_SECONDS = 55;

    /**
     * Run the tasks that have fallen due.
     *
     * A second caller inside the same window is answered successfully without
     * running anything, because from the caller's point of view the schedule
     * has been driven and there is nothing it should retry.
     *
     * @return Response
     */
    public function __invoke(Request $request)
    {
        $lock = Cache::lock('cron-webhook', self::LOCK_SECONDS);

        if (! $lock->get()) {
            return response()->json(['success' => true, 'ran' => false]);
        }

        try {
            Artisan::call('schedule:run');
        } finally {
            // Deliberately not released: the lock is a once-a-minute gate, not
            // a mutual-exclusion guard around a critical section.
        }

        return response()->json(['success' => true, 'ran' => true]);
    }
}
