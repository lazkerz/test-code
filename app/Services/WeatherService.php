<?php

namespace App\Services;

use App\Exceptions\WeatherUnavailableException;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    private const CACHE_KEY = 'weather.current';

    private const STALE_KEY = 'weather.last_known';

    public function current(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached !== null) {
            return $cached;
        }

        try {
            return $this->refresh();
        } catch (WeatherUnavailableException $e) {
            $stale = Cache::get(self::STALE_KEY);

            if ($stale === null) {
                throw $e;
            }

            return $stale + ['stale' => true];
        }
    }

    public function refresh(): array
    {
        $data = $this->fetch();

        Cache::put(self::CACHE_KEY, $data, config('weather.cache_ttl'));
        Cache::put(self::STALE_KEY, $data, config('weather.stale_ttl'));

        return $data;
    }

    private function fetch(): array
    {
        $key = config('weather.api_key');

        if (blank($key)) {
            throw new WeatherUnavailableException('Weather service is not configured.');
        }

        try {
            $response = Http::baseUrl(config('weather.base_url'))
                ->timeout(config('weather.timeout'))
                ->retry(2, 200, throw: false)
                ->get('weather', [
                    'q' => config('weather.city'),
                    'units' => config('weather.units'),
                    'appid' => $key,
                ]);
        } catch (ConnectionException $e) {
            Log::warning('Weather API unreachable', ['error' => $e->getMessage()]);

            throw new WeatherUnavailableException('Weather service is unreachable.', 0, $e);
        }

        if ($response->failed()) {
            Log::warning('Weather API error', ['status' => $response->status()]);

            throw new WeatherUnavailableException('Weather service returned an error.');
        }

        return $this->transform($response->json());
    }

    private function transform(array $payload): array
    {
        return [
            'city' => $payload['name'] ?? null,
            'country' => $payload['sys']['country'] ?? null,
            'temperature' => $payload['main']['temp'] ?? null,
            'feels_like' => $payload['main']['feels_like'] ?? null,
            'humidity' => $payload['main']['humidity'] ?? null,
            'condition' => $payload['weather'][0]['main'] ?? null,
            'description' => $payload['weather'][0]['description'] ?? null,
            'wind_speed' => $payload['wind']['speed'] ?? null,
            'units' => config('weather.units'),
            'observed_at' => isset($payload['dt'])
                ? Carbon::createFromTimestampUTC($payload['dt'])->toIso8601String()
                : null,
        ];
    }
}
