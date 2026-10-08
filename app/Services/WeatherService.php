<?php

namespace App\Services;

use App\Exceptions\WeatherUnavailableException;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    private const CACHE_KEY = 'weather.current';
    private const STALE_KEY = 'weather.last_known';
    private const COOLDOWN_KEY = 'weather.cooldown';
    private const LOCK_KEY = 'weather.refresh';


    public function current(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached !== null) {
            return $cached;
        }

        if (Cache::has(self::COOLDOWN_KEY)) {
            $stale = Cache::get(self::STALE_KEY);

            if ($stale === null) {
                throw new WeatherUnavailableException('Weather service is unavailable and no cached data exists.');
            }

            return $stale + ['stale' => true];
        }


        try {
            return Cache::lock(self::LOCK_KEY, 10)->block(5, function () {
                return Cache::get(self::CACHE_KEY) ?? $this->refresh();
            });
        } catch (WeatherUnavailableException $e) {
            Cache::put(self::COOLDOWN_KEY, true, config('weather.cooldown_ttl'));

            return $this->stale($e);
        } catch (LockTimeoutException $e) {
            return $this->stale(new WeatherUnavailableException('Weather service is currently locked for refresh.'));
        }
    }

    

    public function refresh(): array
    {
        $data = $this->fetch();

        Cache::put(self::CACHE_KEY, $data, config('weather.cache_ttl'));
        Cache::put(self::STALE_KEY, $data, config('weather.stale_ttl'));
        Cache::forget(self::COOLDOWN_KEY);

        return $data;
    }

    private function stale(WeatherUnavailableException $exception): array
    {
        $stale = Cache::get(self::STALE_KEY);

        if ($stale === null) {
            throw $exception;
        }

        return $stale + ['stale' => true];
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
                ->retry(2, 200, function (\Throwable $e){
                    return $e instanceof ConnectionException 
                        || ( $e instanceof RequestException && $e->response?->serverError()
                    );
                }, throw: false)
                ->get('weather', [
                    'q' => config('weather.city'),
                    'units' => config('weather.units'),
                    'appid' => $key,
                ]);
        } catch (ConnectionException $e) {
            // Log::warning('Weather API unreachable', ['error' => $e->getMessage()]);
            Log::warning('Weather API unreachable', ['exception' => $e::class]);

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
