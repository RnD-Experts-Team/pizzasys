<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;



Schedule::command('outbox:publish-pending')
    ->everyFiveMinutes()
    ->withoutOverlapping();