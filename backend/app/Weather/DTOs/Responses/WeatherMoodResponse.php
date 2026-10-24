<?php

namespace App\Weather\DTOs\Responses;

use JsonSerializable;

final readonly class WeatherMoodResponse implements JsonSerializable
{
    public function __construct(
        public string $mood,
        public string $description,
    ) {}

    /**
     * @return array{title: string, mood: string, description: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'title' => 'WEATHER MOOD',
            'mood' => $this->mood,
            'description' => $this->description,
        ];
    }
}
