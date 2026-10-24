<?php

namespace Tests\Feature\Weather;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherTest extends TestCase
{
    public function test_weather_defaults_to_beirut_when_coordinates_are_not_provided(): void
    {
        Http::fake([
            'https://api.open-meteo.com/*' => Http::response([
                'current' => ['temperature_2m' => 18],
            ]),
        ]);

        $response = $this->getJson('/api/weather');

        $response
            ->assertOk()
            ->assertJsonPath('location.latitude', 33.8938)
            ->assertJsonPath('location.longitude', 35.5018)
            ->assertJsonPath('location.is_default', true);

        Http::assertSent(fn ($request) => $request->url() ===
            'https://api.open-meteo.com/v1/forecast?latitude=33.8938&longitude=35.5018&current=temperature_2m%2Crelative_humidity_2m%2Capparent_temperature%2Cprecipitation%2Cweather_code%2Cwind_speed_10m&timezone=Asia%2FBeirut'
        );
    }

    public function test_weather_uses_the_provided_coordinates(): void
    {
        Http::fake([
            'https://api.open-meteo.com/*' => Http::response([
                'current' => ['temperature_2m' => 22],
            ]),
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

    public function test_weather_returns_502_when_open_meteo_fails(): void
    {
        Http::fake([
            'https://api.open-meteo.com/*' => Http::response('boom', 500),
        ]);

        $this->getJson('/api/weather')
            ->assertStatus(502)
            ->assertExactJson(['message' => 'Weather service is temporarily unavailable.']);
    }

    public function test_weather_is_throttled_to_thirty_requests_a_minute(): void
    {
        Http::fake([
            'https://api.open-meteo.com/*' => Http::response(['current' => []]),
        ]);

        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/api/weather')->assertOk();
        }

        $this->getJson('/api/weather')->assertStatus(429);
    }
}
