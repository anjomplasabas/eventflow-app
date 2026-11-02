<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('eventflow:expire-pending-registrations')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('eventflow:send-event-reminders')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('eventflow:mark-events-completed')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('eventflow:delete-expired-exports')
    ->daily()
    ->withoutOverlapping()
    ->runInBackground();
