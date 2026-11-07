<?php

namespace App\Weather\DTOs\Responses;

use Carbon\CarbonImmutable;
use JsonSerializable;

/** Normalizes WeatherAPI.com data into the weather contract shared with the frontend. */
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
    public static function fromWeatherApi(array $data): ?self
    {
        foreach (['temp_c', 'humidity', 'feelslike_c', 'wind_kph', 'precip_mm', 'condition.code', 'last_updated_epoch'] as $field) {
            if (! is_numeric(data_get($data, 'current.'.$field))) {
                return null;
            }
        }

        $current = $data['current'];

        return new self(
            CarbonImmutable::createFromTimestampUTC((int) $current['last_updated_epoch'])
                ->setTimezone(config('services.weatherapi.timezone'))->format('Y-m-d\TH:i'),
            (float) $current['temp_c'],
            (int) $current['humidity'],
            (float) $current['feelslike_c'],
            (float) $current['precip_mm'],
            self::weatherCode((int) $current['condition']['code']),
            (float) $current['wind_kph'],
        );
    }

    /** Closest WMO equivalents, keeping existing weather icons and recommendation rules compatible. */
    private static function weatherCode(int $code): int
    {
        return match ($code) {
            1000 => 0,
            1003 => 2,
            1006, 1009 => 3,
            1030, 1135 => 45,
            1147 => 48,
            1150, 1153 => 51,
            1072, 1168 => 56,
            1171 => 57,
            1063, 1180, 1183 => 61,
            1186, 1189 => 63,
            1192, 1195 => 65,
            1198 => 66,
            1201 => 67,
            1240 => 80,
            1243 => 81,
            1246 => 82,
            1066, 1210, 1213 => 71,
            1114, 1216, 1219 => 73,
            1117, 1222, 1225 => 75,
            1069, 1204, 1207, 1237, 1249, 1252, 1261, 1264 => 77,
            1255 => 85,
            1258 => 86,
            1087, 1273, 1276, 1279, 1282 => 95,
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
