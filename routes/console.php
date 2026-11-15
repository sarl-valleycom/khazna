<?php

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
    ->withoutOverlapping();
