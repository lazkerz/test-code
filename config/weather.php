<?php

return [
    'api_key' => env('WEATHER_API_KEY'),
    'base_url' => env('WEATHER_BASE_URL', 'https://api.openweathermap.org/data/2.5'),
    'city' => env('WEATHER_CITY', 'Perth,AU'),
    'units' => env('WEATHER_UNITS', 'metric'),
    'cache_ttl' => (int) env('WEATHER_CACHE_TTL', 900),
    'stale_ttl' => (int) env('WEATHER_STALE_TTL', 86400),
    'timeout' => (int) env('WEATHER_TIMEOUT', 5),
];
