<?php

namespace Tests\Unit\Weather;

use App\Gemini\Exceptions\GeminiUnavailable;
use App\Gemini\InterfaceServices\GeminiServiceInterface;
use App\Weather\DTOs\Requests\WeatherMoodQuery;
use App\Weather\Exceptions\WeatherMoodUnavailable;
use App\Weather\Services\WeatherMoodService;
use Tests\TestCase;

class WeatherMoodServiceTest extends TestCase
{
    public function test_it_builds_the_prompt_and_maps_the_answer(): void
    {
        $gemini = new class implements GeminiServiceInterface
        {
            public string $prompt = '';

            public function ask(string $prompt): array
            {
                $this->prompt = $prompt;

                return ['mood' => 'Cozy dramas', 'description' => 'Slow picks.'];
            }
        };

        $response = (new WeatherMoodService($gemini))->mood(new WeatherMoodQuery('Rain', 12.5, 80, 10, 2));

        $this->assertSame('Cozy dramas', $response->mood);
        $this->assertSame('Slow picks.', $response->description);
        $this->assertStringContainsString('12.5°C, Rain', $gemini->prompt);
        $this->assertStringContainsString('humidity 80%', $gemini->prompt);
        $this->assertStringContainsString('wind 10 km/h', $gemini->prompt);
        $this->assertStringContainsString('precipitation 2 mm', $gemini->prompt);
        $this->assertStringContainsString('{"mood":"","description":""}', $gemini->prompt);
    }

    public function test_missing_keys_fall_back_to_empty_strings(): void
    {
        $gemini = new class implements GeminiServiceInterface
        {
            public function ask(string $prompt): array
            {
                return [];
            }
        };

        $response = (new WeatherMoodService($gemini))->mood(new WeatherMoodQuery('Sun', 20, 40, 5, 0));

        $this->assertSame('', $response->mood);
        $this->assertSame('', $response->description);
    }

    public function test_gemini_failure_becomes_weather_mood_unavailable(): void
    {
        $gemini = new class implements GeminiServiceInterface
        {
            public function ask(string $prompt): array
            {
                throw new GeminiUnavailable('SECRET');
            }
        };

        try {
            (new WeatherMoodService($gemini))->mood(new WeatherMoodQuery('Sun', 20, 40, 5, 0));
            $this->fail('Expected WeatherMoodUnavailable.');
        } catch (WeatherMoodUnavailable $e) {
            $this->assertStringNotContainsString('SECRET', $e->getMessage());
        }
    }
}
