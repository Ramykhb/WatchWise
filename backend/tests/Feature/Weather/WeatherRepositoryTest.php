<?php

namespace Tests\Feature\Weather;

use App\Weather\Exceptions\WeatherUnavailable;
use App\Weather\Repository\WeatherRepository;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherRepositoryTest extends TestCase
{
    public function test_it_requests_open_meteo_and_returns_the_current_block(): void
    {
        Http::fake([
            'https://api.open-meteo.com/*' => Http::response(['current' => ['temperature_2m' => 5]]),
        ]);

        $current = (new WeatherRepository)->current(40.7128, -74.006);

        $this->assertSame(['temperature_2m' => 5], $current);
        Http::assertSent(fn ($request) => $request->url() ===
            'https://api.open-meteo.com/v1/forecast?latitude=40.7128&longitude=-74.006&current=temperature_2m%2Crelative_humidity_2m%2Capparent_temperature%2Cprecipitation%2Cweather_code%2Cwind_speed_10m&timezone=Asia%2FBeirut'
        );
    }

    public function test_it_throws_a_generic_exception_when_the_request_fails(): void
    {
        Http::fake(['https://api.open-meteo.com/*' => Http::response('secret body', 500)]);

        try {
            (new WeatherRepository)->current(1.0, 2.0);
            $this->fail('Expected WeatherUnavailable.');
        } catch (WeatherUnavailable $e) {
            $this->assertSame('Open-Meteo request failed', $e->getMessage());
        }
    }
}
