<?php

namespace Tests\Feature\Weather;

use App\Weather\Exceptions\WeatherUnavailable;
use App\Weather\Repository\WeatherRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class WeatherRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.weatherapi.key' => 'test-weather-key',
            'services.weatherapi.url' => 'https://api.weatherapi.com/v1/current.json',
            'services.weatherapi.timezone' => 'Asia/Beirut',
        ]);
        Http::preventStrayRequests();
    }

    /** @return array<string, mixed> */
    private function weatherPayload(int $code = 1183): array
    {
        return [
            'current' => [
                'last_updated_epoch' => 1704067200,
                'temp_c' => 5,
                'humidity' => 80,
                'feelslike_c' => 3,
                'wind_kph' => 9,
                'precip_mm' => 1.5,
                'condition' => ['code' => $code],
            ],
        ];
    }

    public function test_it_requests_weatherapi_and_normalizes_units_and_conditions(): void
    {
        Http::fake([
            'https://api.weatherapi.com/*' => Http::response($this->weatherPayload()),
        ]);

        $current = (new WeatherRepository)->current(40.7128, -74.006);

        $this->assertSame([
            'time' => '2024-01-01T02:00',
            'temperature_2m' => 5.0,
            'relative_humidity_2m' => 80,
            'apparent_temperature' => 3.0,
            'precipitation' => 1.5,
            'weather_code' => 61,
            'wind_speed_10m' => 9.0,
        ], $current);
        Http::assertSent(fn ($request) => $request->url() ===
            'https://api.weatherapi.com/v1/current.json?key=test-weather-key&q=40.7128%2C-74.006&aqi=no'
        );
    }

    public function test_it_throws_a_generic_exception_when_the_request_fails(): void
    {
        Http::fake(['https://api.weatherapi.com/*' => Http::response('secret body', 500)]);

        try {
            (new WeatherRepository)->current(1.0, 2.0);
            $this->fail('Expected WeatherUnavailable.');
        } catch (WeatherUnavailable $e) {
            $this->assertSame('WeatherAPI.com request failed', $e->getMessage());
        }
    }

    public function test_rate_limit_diagnostics_are_written_to_stderr_without_request_details(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://api.weatherapi.com/*' => Http::response([
            'error' => ['code' => 2007, 'message' => 'Quota exceeded for test-weather-key.'],
            'private_field' => 'must-not-be-logged',
        ], 403)]);
        $this->expectFailureLog([
            'host' => 'api.weatherapi.com',
            'failure_type' => 'http',
            'upstream_status' => 403,
            'upstream_reason' => 'Quota exceeded for [redacted].',
            'curl_error' => null,
        ]);

        $this->getJson('/api/weather?latitude=33.8938&longitude=35.5018')
            ->assertStatus(502)
            ->assertExactJson(['message' => 'Weather service is temporarily unavailable.']);
    }

    public function test_connection_diagnostics_include_the_curl_code_without_the_request_url(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://api.weatherapi.com/*' => Http::failedConnection(
            'cURL error 6: Could not resolve host: api.weatherapi.com for https://api.weatherapi.com/v1/current.json?q=33.8938,35.5018&key=test-weather-key',
        )]);
        $this->expectFailureLog([
            'host' => 'api.weatherapi.com',
            'failure_type' => 'connection',
            'upstream_status' => null,
            'upstream_reason' => null,
            'curl_error' => 6,
        ]);

        $this->getJson('/api/weather')
            ->assertStatus(502)
            ->assertExactJson(['message' => 'Weather service is temporarily unavailable.']);
    }

    public function test_missing_key_fails_without_sending_a_request(): void
    {
        config(['services.weatherapi.key' => '']);
        Http::fake();

        $this->getJson('/api/weather')->assertStatus(502)
            ->assertExactJson(['message' => 'Weather service is temporarily unavailable.']);

        Http::assertNothingSent();
    }

    public function test_malformed_provider_data_returns_502_instead_of_fabricated_weather(): void
    {
        Http::fake(['https://api.weatherapi.com/*' => Http::response(['main' => ['temp' => 5]])]);

        $this->getJson('/api/weather')->assertStatus(502)
            ->assertExactJson(['message' => 'Weather service is temporarily unavailable.']);
    }

    public function test_dry_weather_preserves_zero_precipitation(): void
    {
        $payload = $this->weatherPayload(1000);
        $payload['current']['precip_mm'] = 0;
        Http::fake(['https://api.weatherapi.com/*' => Http::response($payload)]);

        $this->getJson('/api/weather')->assertOk()
            ->assertJsonPath('current.precipitation', 0)
            ->assertJsonPath('current.weather_code', 0);
    }

    #[DataProvider('conditionCodes')]
    public function test_conditions_keep_existing_frontend_weather_categories(int $providerCode, int $expectedCode): void
    {
        Http::fake(['https://api.weatherapi.com/*' => Http::response($this->weatherPayload($providerCode))]);

        $this->getJson('/api/weather')->assertOk()
            ->assertJsonPath('current.weather_code', $expectedCode);
    }

    /** @return array<string, array{int, int}> */
    public static function conditionCodes(): array
    {
        return [
            'clear' => [1000, 0], 'partly cloudy' => [1003, 2],
            'cloudy' => [1006, 3], 'overcast' => [1009, 3], 'mist' => [1030, 45],
            'fog' => [1135, 45], 'freezing fog' => [1147, 48], 'drizzle' => [1153, 51],
            'freezing drizzle' => [1168, 56], 'heavy freezing drizzle' => [1171, 57],
            'patchy rain' => [1063, 61], 'light rain' => [1183, 61], 'rain' => [1189, 63],
            'heavy rain' => [1195, 65], 'light freezing rain' => [1198, 66],
            'heavy freezing rain' => [1201, 67], 'light shower' => [1240, 80],
            'shower' => [1243, 81], 'heavy shower' => [1246, 82],
            'light snow' => [1213, 71], 'snow' => [1219, 73], 'heavy snow' => [1225, 75],
            'sleet' => [1204, 77], 'ice pellets' => [1237, 77],
            'light snow shower' => [1255, 85], 'snow shower' => [1258, 86],
            'thunderstorm' => [1276, 95], 'snow with thunder' => [1282, 95],
            'unknown' => [9999, -1],
        ];
    }

    /** @param array<string, mixed> $context */
    private function expectFailureLog(array $context): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        Log::shouldReceive('channel')->once()->with('stderr')->andReturn($logger);
        Log::shouldReceive('error')->once()
            ->with('WeatherAPI.com request failed', Mockery::type('array'));
        $logger->shouldReceive('warning')->once()->with('WeatherAPI.com request failed.', $context);
    }
}
