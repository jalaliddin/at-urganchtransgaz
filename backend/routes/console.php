<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('helpdesk:check-sla')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('helpdesk:auto-close')->hourly()->withoutOverlapping();
Schedule::command('exports:prune')->dailyAt('03:30');
Schedule::command('assets:warranty-check')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('warehouse:stock-check')->dailyAt('08:05')->withoutOverlapping();
Schedule::command('software:license-check')->dailyAt('08:10')->withoutOverlapping();
