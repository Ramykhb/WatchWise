<?php

namespace Tests\Feature\Weather;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.openweather.key' => 'test-weather-key',
            'services.openweather.url' => 'https://api.openweathermap.org/data/2.5/weather',
            'services.openweather.default_latitude' => 33.8938,
            'services.openweather.default_longitude' => 35.5018,
            'services.openweather.timezone' => 'Asia/Beirut',
        ]);
        Http::preventStrayRequests();
    }

    /** @return array<string, mixed> */
    private function weatherPayload(float $temperature = 18): array
    {
        return [
            'dt' => 1704067200,
            'main' => ['temp' => $temperature, 'humidity' => 60, 'feels_like' => 17],
            'wind' => ['speed' => 2],
            'weather' => [['id' => 801]],
        ];
    }

    public function test_weather_defaults_to_beirut_when_coordinates_are_not_provided(): void
    {
        Http::fake([
            'https://api.openweathermap.org/*' => Http::response($this->weatherPayload()),
        ]);

        $response = $this->getJson('/api/weather');

        $response
            ->assertOk()
            ->assertJsonPath('location.latitude', 33.8938)
            ->assertJsonPath('location.longitude', 35.5018)
            ->assertJsonPath('location.is_default', true);

        Http::assertSent(fn ($request) => $request->url() ===
            'https://api.openweathermap.org/data/2.5/weather?lat=33.8938&lon=35.5018&appid=test-weather-key&units=metric'
        );
    }

    public function test_weather_uses_the_provided_coordinates(): void
    {
        Http::fake([
            'https://api.openweathermap.org/*' => Http::response($this->weatherPayload(22)),
        ]);

        $response = $this->getJson('/api/weather?latitude=40.7128&longitude=-74.006');

        $response
            ->assertOk()
            ->assertJsonPath('location.latitude', 40.7128)
            ->assertJsonPath('location.longitude', -74.006)
            ->assertJsonPath('location.is_default', false);
    }

    public function test_weather_requires_both_coordinates_when_one_is_provided(): void
    {
        $this->getJson('/api/weather?latitude=33.8938')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['longitude']);
    }

    public function test_weather_returns_502_when_openweather_fails(): void
    {
        Http::fake([
            'https://api.openweathermap.org/*' => Http::response('boom', 500),
        ]);

        $this->getJson('/api/weather')
            ->assertStatus(502)
            ->assertExactJson(['message' => 'Weather service is temporarily unavailable.']);
    }

    public function test_weather_is_throttled_to_thirty_requests_a_minute(): void
    {
        Http::fake([
            'https://api.openweathermap.org/*' => Http::response($this->weatherPayload()),
        ]);

        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/api/weather')->assertOk();
        }

        $this->getJson('/api/weather')->assertStatus(429);
    }
}
