<?php

use Illuminate\Support\Facades\Schedule;

// The inline queue worker is started by the command itself in cron mode
// only (TSO_SCHEDULER_MODE=cron); queue mode relies on a dedicated worker.
Schedule::command('tso:run-scheduler')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->onOneServer();
