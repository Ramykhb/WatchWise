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
            'services.openweather.key' => 'test-weather-key',
            'services.openweather.url' => 'https://api.openweathermap.org/data/2.5/weather',
            'services.openweather.timezone' => 'Asia/Beirut',
        ]);
        Http::preventStrayRequests();
    }

    /** @return array<string, mixed> */
    private function weatherPayload(int $code = 500): array
    {
        return [
            'dt' => 1704067200,
            'main' => ['temp' => 5, 'humidity' => 80, 'feels_like' => 3],
            'wind' => ['speed' => 2.5],
            'weather' => [['id' => $code]],
            'rain' => ['1h' => 1.2],
            'snow' => ['1h' => 0.3],
        ];
    }

    public function test_it_requests_openweather_and_normalizes_units_and_conditions(): void
    {
        Http::fake([
            'https://api.openweathermap.org/*' => Http::response($this->weatherPayload()),
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
            'https://api.openweathermap.org/data/2.5/weather?lat=40.7128&lon=-74.006&appid=test-weather-key&units=metric'
        );
    }

    public function test_it_throws_a_generic_exception_when_the_request_fails(): void
    {
        Http::fake(['https://api.openweathermap.org/*' => Http::response('secret body', 500)]);

        try {
            (new WeatherRepository)->current(1.0, 2.0);
            $this->fail('Expected WeatherUnavailable.');
        } catch (WeatherUnavailable $e) {
            $this->assertSame('OpenWeatherMap request failed', $e->getMessage());
        }
    }

    public function test_rate_limit_diagnostics_are_written_to_stderr_without_request_details(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://api.openweathermap.org/*' => Http::response([
            'message' => 'Quota exceeded for test-weather-key.',
            'private_field' => 'must-not-be-logged',
        ], 429)]);
        $this->expectFailureLog([
            'host' => 'api.openweathermap.org',
            'failure_type' => 'http',
            'upstream_status' => 429,
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
        Http::fake(['https://api.openweathermap.org/*' => Http::failedConnection(
            'cURL error 6: Could not resolve host: api.openweathermap.org for https://api.openweathermap.org/data/2.5/weather?lat=33.8938&appid=test-weather-key',
        )]);
        $this->expectFailureLog([
            'host' => 'api.openweathermap.org',
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
        config(['services.openweather.key' => '']);
        Http::fake();

        $this->getJson('/api/weather')->assertStatus(502)
            ->assertExactJson(['message' => 'Weather service is temporarily unavailable.']);

        Http::assertNothingSent();
    }

    public function test_malformed_provider_data_returns_502_instead_of_fabricated_weather(): void
    {
        Http::fake(['https://api.openweathermap.org/*' => Http::response(['main' => ['temp' => 5]])]);

        $this->getJson('/api/weather')->assertStatus(502)
            ->assertExactJson(['message' => 'Weather service is temporarily unavailable.']);
    }

    public function test_dry_weather_without_optional_rain_or_snow_defaults_to_zero_precipitation(): void
    {
        $payload = $this->weatherPayload(800);
        unset($payload['rain'], $payload['snow']);
        Http::fake(['https://api.openweathermap.org/*' => Http::response($payload)]);

        $this->getJson('/api/weather')->assertOk()
            ->assertJsonPath('current.precipitation', 0)
            ->assertJsonPath('current.weather_code', 0);
    }

    #[DataProvider('conditionCodes')]
    public function test_conditions_keep_existing_frontend_weather_categories(int $providerCode, int $expectedCode): void
    {
        Http::fake(['https://api.openweathermap.org/*' => Http::response($this->weatherPayload($providerCode))]);

        $this->getJson('/api/weather')->assertOk()
            ->assertJsonPath('current.weather_code', $expectedCode);
    }

    /** @return array<string, array{int, int}> */
    public static function conditionCodes(): array
    {
        return [
            'clear' => [800, 0], 'few clouds' => [801, 1], 'scattered clouds' => [802, 2],
            'broken clouds' => [803, 3], 'overcast' => [804, 3], 'mist' => [701, 45], 'fog' => [741, 45],
            'light drizzle' => [300, 51], 'drizzle' => [301, 53], 'heavy drizzle' => [302, 55],
            'light rain' => [500, 61], 'rain' => [501, 63], 'heavy rain' => [502, 65],
            'freezing rain' => [511, 66], 'light shower' => [520, 80], 'shower' => [521, 81],
            'heavy shower' => [522, 82], 'light snow' => [600, 71], 'snow' => [601, 73],
            'heavy snow' => [602, 75], 'sleet' => [611, 77], 'light snow shower' => [620, 85],
            'snow shower' => [621, 86], 'thunderstorm' => [211, 95], 'unknown' => [999, -1],
        ];
    }

    /** @param array<string, mixed> $context */
    private function expectFailureLog(array $context): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        Log::shouldReceive('channel')->once()->with('stderr')->andReturn($logger);
        Log::shouldReceive('error')->once()
            ->with('OpenWeatherMap request failed', Mockery::type('array'));
        $logger->shouldReceive('warning')->once()->with('OpenWeatherMap request failed.', $context);
    }
}
