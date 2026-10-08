<?php

use App\Jobs\RefreshWeatherData;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new RefreshWeatherData)->everyTenMinutes()->withoutOverlapping();
Schedule::command('sanctum:prune-expired --hours=24')->daily();