<?php

namespace Tests\Feature\Weather;

use App\Weather\Exceptions\WeatherUnavailable;
use App\Weather\Repository\WeatherRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Psr\Log\LoggerInterface;
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

    public function test_rate_limit_diagnostics_are_written_to_stderr_without_request_details(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://api.open-meteo.com/*' => Http::response([
            'reason' => 'Daily API request limit exceeded.',
            'private_field' => 'must-not-be-logged',
        ], 429)]);
        $this->expectFailureLog([
            'host' => 'api.open-meteo.com',
            'failure_type' => 'http',
            'upstream_status' => 429,
            'upstream_reason' => 'Daily API request limit exceeded.',
            'curl_error' => null,
        ]);

        $this->getJson('/api/weather?latitude=33.8938&longitude=35.5018')
            ->assertStatus(502)
            ->assertExactJson(['message' => 'Weather service is temporarily unavailable.']);
    }

    public function test_connection_diagnostics_include_the_curl_code_without_the_request_url(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://api.open-meteo.com/*' => Http::failedConnection(
            'cURL error 6: Could not resolve host: api.open-meteo.com for https://api.open-meteo.com/v1/forecast?latitude=33.8938',
        )]);
        $this->expectFailureLog([
            'host' => 'api.open-meteo.com',
            'failure_type' => 'connection',
            'upstream_status' => null,
            'upstream_reason' => null,
            'curl_error' => 6,
        ]);

        $this->getJson('/api/weather')
            ->assertStatus(502)
            ->assertExactJson(['message' => 'Weather service is temporarily unavailable.']);
    }

    /** @param array<string, mixed> $context */
    private function expectFailureLog(array $context): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        Log::shouldReceive('channel')->once()->with('stderr')->andReturn($logger);
        Log::shouldReceive('error')->once()
            ->with('Open-Meteo request failed', Mockery::type('array'));
        $logger->shouldReceive('warning')->once()->with('Open-Meteo request failed.', $context);
    }
}
