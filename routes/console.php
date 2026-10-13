<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('cms:check-notifications')->everyFifteenMinutes();
Schedule::command('cms:check-updates')->dailyAt('04:20');
Schedule::command('activity-log:prune')->daily();
