<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('tso:run-scheduler --work')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->onOneServer();
