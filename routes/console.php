<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// withoutOverlapping() prevents a new scheduler instance from starting if the
// previous run is still executing (e.g. because sleep() inside work-mails is
// blocking). The lock expiry (minutes) is set conservatively above each
// command's expected worst-case runtime.
Schedule::command('queue:work-mails --limit=60')
    ->everyMinute()
    ->withoutOverlapping(10); // max 10-min lock — covers the 5-min job timeout

Schedule::command('queue:process-drips')
    ->everyMinute()
    ->withoutOverlapping(5);  // max 5-min lock

Schedule::command('campaigns:dispatch-scheduled')
    ->everyMinute()
    ->withoutOverlapping(2);  // max 2-min lock — this command is quick
