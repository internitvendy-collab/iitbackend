<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('library:health', function () {
    $this->info('IIT Shelf Laravel API is ready.');
})->purpose('Check the library API application boundary');

Schedule::command('notifications:dispatch --type=due')->dailyAt('07:00');
Schedule::command('notifications:dispatch --type=fines')->dailyAt('06:00');
Schedule::command('notifications:dispatch --type=reservations')->everySixHours();
