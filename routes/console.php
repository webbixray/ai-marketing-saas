<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule the scheduled reports command to run on the 1st of every month at 06:00
Schedule::command('reports:generate-scheduled')
    ->monthlyOn(1, '06:00')
    ->timezone('UTC')
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Scheduled report generation failed');
    })
    ->onSuccess(function () {
        \Illuminate\Support\Facades\Log::info('Scheduled report generation completed successfully');
    });
