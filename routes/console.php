<?php

use App\Models\AttendanceSetting;
use App\Support\AlgerianDate;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('ledger:verify')
    ->dailyAt('03:00')
    ->timezone('Africa/Algiers')
    ->withoutOverlapping();

Schedule::command('dues:generate')
    ->dailyAt('01:00')
    ->timezone('Africa/Algiers')
    ->withoutOverlapping();

Schedule::command('attendance:auto-close')
    ->everyMinute()
    ->timezone('Africa/Algiers')
    ->withoutOverlapping()
    ->when(fn (): bool => AlgerianDate::now()->format('H:i') === substr(AttendanceSetting::current()->auto_close_time, 0, 5));

Schedule::command('attendance:correction-reminders')
    ->dailyAt('08:00')
    ->timezone('Africa/Algiers')
    ->withoutOverlapping();

Schedule::command('attendance:remind')
    ->everyFiveMinutes()
    ->timezone('Africa/Algiers')
    ->withoutOverlapping();

Schedule::command('attendance:digest-daily')
    ->everyMinute()
    ->timezone('Africa/Algiers')
    ->withoutOverlapping()
    ->when(fn (): bool => AlgerianDate::now()->format('H:i') === substr(AttendanceSetting::current()->digest_time, 0, 5));

Schedule::command('attendance:digest-weekly')
    ->sundays()
    ->at('08:00')
    ->timezone('Africa/Algiers')
    ->withoutOverlapping();
