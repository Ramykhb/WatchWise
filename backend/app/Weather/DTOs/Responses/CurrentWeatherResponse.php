<?php

namespace App\Weather\DTOs\Responses;

use Carbon\CarbonImmutable;
use JsonSerializable;

/** Normalizes OpenWeatherMap data into the weather contract shared with the frontend. */
final readonly class CurrentWeatherResponse implements JsonSerializable
{
    public function __construct(
        public string $time,
        public float $temperature,
        public int $humidity,
        public float $feelsLike,
        public float $precipitation,
        public int $weatherCode,
        public float $windSpeed,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromOpenWeather(array $data): ?self
    {
        foreach (['main.temp', 'main.humidity', 'main.feels_like', 'wind.speed', 'weather.0.id', 'dt'] as $field) {
            if (! is_numeric(data_get($data, $field))) {
                return null;
            }
        }

        $rain = data_get($data, 'rain.1h', 0);
        $snow = data_get($data, 'snow.1h', 0);
        if (! is_numeric($rain) || ! is_numeric($snow)) {
            return null;
        }

        return new self(
            CarbonImmutable::createFromTimestampUTC((int) $data['dt'])
                ->setTimezone(config('services.openweather.timezone'))->format('Y-m-d\TH:i'),
            (float) $data['main']['temp'],
            (int) $data['main']['humidity'],
            (float) $data['main']['feels_like'],
            (float) $rain + (float) $snow,
            self::weatherCode((int) $data['weather'][0]['id']),
            round((float) $data['wind']['speed'] * 3.6, 1),
        );
    }

    /** Closest WMO equivalents, keeping existing weather icons and recommendation rules compatible. */
    private static function weatherCode(int $code): int
    {
        return match ($code) {
            800 => 0,
            801 => 1,
            802 => 2,
            803, 804 => 3,
            701, 741 => 45,
            300, 310 => 51,
            301, 311, 313, 321 => 53,
            302, 312, 314 => 55,
            500 => 61,
            501 => 63,
            502, 503, 504 => 65,
            511 => 66,
            520 => 80,
            521 => 81,
            522, 531 => 82,
            600 => 71,
            601 => 73,
            602 => 75,
            611, 612, 613, 615, 616 => 77,
            620 => 85,
            621, 622 => 86,
            200, 201, 202, 210, 211, 212, 221, 230, 231, 232 => 95,
            default => -1,
        };
    }

    /** @return array<string, int|float|string> */
    public function jsonSerialize(): array
    {
        return [
            'time' => $this->time,
            'temperature_2m' => $this->temperature,
            'relative_humidity_2m' => $this->humidity,
            'apparent_temperature' => $this->feelsLike,
            'precipitation' => $this->precipitation,
            'weather_code' => $this->weatherCode,
            'wind_speed_10m' => $this->windSpeed,
        ];
    }
}
