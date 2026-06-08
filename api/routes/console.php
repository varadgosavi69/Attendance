<?php

use App\Console\Commands\SendDailyAttendance;
use App\Jobs\PredictDetentionRiskJob;
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

// Phase 6 — score every student's detention risk nightly via the ml-service
// (SCALABLE_ARCHITECTURE.md §9: `ml:predict-risks` dailyAt('02:00'))
Schedule::job(new PredictDetentionRiskJob())
    ->dailyAt('02:00')
    ->onOneServer()
    ->withoutOverlapping();
