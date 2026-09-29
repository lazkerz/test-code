<?php

namespace Tests\Feature;

use App\Jobs\RefreshWeatherData;
use App\Services\WeatherService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['weather.api_key' => 'test-key']);
    }

    private function fakeSuccess(): void
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response([
                'name' => 'Perth',
                'sys' => ['country' => 'AU'],
                'main' => ['temp' => 21.5, 'feels_like' => 20.9, 'humidity' => 55],
                'weather' => [['main' => 'Clear', 'description' => 'clear sky']],
                'wind' => ['speed' => 4.1],
                'dt' => 1735689600,
            ]),
        ]);
    }

    public function test_returns_current_weather_for_perth(): void
    {
        $this->fakeSuccess();

        $this->getJson('/api/weather')
            ->assertOk()
            ->assertJsonPath('data.city', 'Perth')
            ->assertJsonPath('data.country', 'AU')
            ->assertJsonPath('data.temperature', 21.5)
            ->assertJsonPath('data.condition', 'Clear');

        Http::assertSent(fn (Request $request) => $request['q'] === 'Perth,AU'
            && $request['appid'] === 'test-key');
    }

    public function test_second_request_is_served_from_cache(): void
    {
        $this->fakeSuccess();

        $this->getJson('/api/weather')->assertOk();
        $this->getJson('/api/weather')->assertOk();

        Http::assertSentCount(1);
    }

    public function test_returns_503_when_external_api_fails(): void
    {
        Http::fake(['api.openweathermap.org/*' => Http::response(['message' => 'error'], 500)]);

        $this->getJson('/api/weather')
            ->assertStatus(503)
            ->assertJsonStructure(['message']);
    }

    public function test_returns_503_when_api_key_is_missing(): void
    {
        config(['weather.api_key' => null]);
        Http::fake();

        $this->getJson('/api/weather')->assertStatus(503);

        Http::assertNothingSent();
    }

    public function test_serves_last_known_data_marked_stale_when_api_fails(): void
    {
        Cache::put('weather.last_known', ['city' => 'Perth', 'temperature' => 19.0], 3600);
        Http::fake(['api.openweathermap.org/*' => Http::response([], 500)]);

        $this->getJson('/api/weather')
            ->assertOk()
            ->assertJsonPath('data.city', 'Perth')
            ->assertJsonPath('data.stale', true);
    }

    public function test_refresh_job_updates_cache(): void
    {
        $this->fakeSuccess();

        (new RefreshWeatherData)->handle(app(WeatherService::class));

        $this->assertNotNull(Cache::get('weather.current'));
        Http::assertSentCount(1);
    }
}
