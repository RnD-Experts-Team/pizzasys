<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('outbox:publish-pending')
    ->everyFiveMinutes()
    ->withoutOverlapping();