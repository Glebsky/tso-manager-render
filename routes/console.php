<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('tso:run-scheduler --work')
    ->everyTwoMinutes()
    ->withoutOverlapping(10)
    ->onOneServer();
