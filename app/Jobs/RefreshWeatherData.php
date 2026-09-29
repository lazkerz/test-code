<?php

namespace App\Jobs;

use App\Services\WeatherService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshWeatherData implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(WeatherService $weather): void
    {
        $weather->refresh();
    }
}
