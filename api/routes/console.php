<?php

use App\Console\Commands\SendDailyAttendance;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Queue daily attendance emails every weekday at 17:00
Schedule::command(SendDailyAttendance::class)
    ->weekdays()
    ->at('17:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/attendance-emails.log'));
