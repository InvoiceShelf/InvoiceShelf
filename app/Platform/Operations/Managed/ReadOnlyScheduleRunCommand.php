<?php

namespace App\Platform\Operations\Managed;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Console\Scheduling\ScheduleRunCommand;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;

/** Covers every scheduled task, including schedules registered by modules. */
class ReadOnlyScheduleRunCommand extends ScheduleRunCommand
{
    public function handle(Schedule $schedule, Dispatcher $dispatcher, Cache $cache, ExceptionHandler $handler): void
    {
        if (ManagedMode::readOnly()) {
            $this->components->info('Scheduled work is skipped while this instance is read-only.');

            return;
        }

        parent::handle($schedule, $dispatcher, $cache, $handler);
    }
}
