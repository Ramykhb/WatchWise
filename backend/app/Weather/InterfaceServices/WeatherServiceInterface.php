<?php

namespace App\Weather\InterfaceServices;

use App\Weather\DTOs\Requests\WeatherQuery;
use App\Weather\DTOs\Responses\WeatherResponse;
use App\Weather\Exceptions\WeatherUnavailable;

interface WeatherServiceInterface
{
    /**
     * The current weather. A query without coordinates uses the configured default location.
     *
     * @throws WeatherUnavailable
     */
    public function current(WeatherQuery $query): WeatherResponse;
}
