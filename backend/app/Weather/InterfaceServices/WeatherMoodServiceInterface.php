<?php

namespace App\Weather\InterfaceServices;

use App\Weather\DTOs\Requests\WeatherMoodQuery;
use App\Weather\DTOs\Responses\WeatherMoodResponse;
use App\Weather\Exceptions\WeatherMoodUnavailable;

interface WeatherMoodServiceInterface
{
    /**
     * Ask Groq which kind of movies this weather calls for.
     *
     * @throws WeatherMoodUnavailable
     */
    public function mood(WeatherMoodQuery $query): WeatherMoodResponse;
}
