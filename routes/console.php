<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('ops:process-reminders')->everyMinute()->withoutOverlapping();
Schedule::command('ops:process-recurring')->everyMinute()->withoutOverlapping();
Schedule::command('ops:send-briefings')->everyMinute()->withoutOverlapping();
Schedule::command('ops:backup-database --if-needed')->hourly()->withoutOverlapping();
Schedule::command('ops:maintenance')->dailyAt('03:00')->withoutOverlapping();
