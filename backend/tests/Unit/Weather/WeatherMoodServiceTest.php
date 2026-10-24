<?php

namespace Tests\Unit\Weather;

use App\Groq\Exceptions\GroqUnavailable;
use App\Groq\InterfaceServices\GroqServiceInterface;
use App\Weather\DTOs\Requests\WeatherMoodQuery;
use App\Weather\Exceptions\WeatherMoodUnavailable;
use App\Weather\Services\WeatherMoodService;
use Tests\TestCase;

class WeatherMoodServiceTest extends TestCase
{
    public function test_it_builds_the_prompt_and_maps_the_answer(): void
    {
        $groq = new class implements GroqServiceInterface
        {
            public string $prompt = '';

            public function ask(string $prompt): array
            {
                $this->prompt = $prompt;

                return ['mood' => 'Cozy dramas', 'description' => 'Slow picks.'];
            }
        };

        $response = (new WeatherMoodService($groq))->mood(new WeatherMoodQuery('Rain', 12.5, 80, 10, 2));

        $this->assertSame('Cozy dramas', $response->mood);
        $this->assertSame('Slow picks.', $response->description);
        $this->assertStringContainsString('12.5°C, Rain', $groq->prompt);
        $this->assertStringContainsString('humidity 80%', $groq->prompt);
        $this->assertStringContainsString('wind 10 km/h', $groq->prompt);
        $this->assertStringContainsString('precipitation 2 mm', $groq->prompt);
        $this->assertStringContainsString('{"mood":"","description":""}', $groq->prompt);
    }

    public function test_missing_keys_fall_back_to_empty_strings(): void
    {
        $groq = new class implements GroqServiceInterface
        {
            public function ask(string $prompt): array
            {
                return [];
            }
        };

        $response = (new WeatherMoodService($groq))->mood(new WeatherMoodQuery('Sun', 20, 40, 5, 0));

        $this->assertSame('', $response->mood);
        $this->assertSame('', $response->description);
    }

    public function test_groq_failure_becomes_weather_mood_unavailable(): void
    {
        $groq = new class implements GroqServiceInterface
        {
            public function ask(string $prompt): array
            {
                throw new GroqUnavailable('SECRET');
            }
        };

        try {
            (new WeatherMoodService($groq))->mood(new WeatherMoodQuery('Sun', 20, 40, 5, 0));
            $this->fail('Expected WeatherMoodUnavailable.');
        } catch (WeatherMoodUnavailable $e) {
            $this->assertStringNotContainsString('SECRET', $e->getMessage());
        }
    }
}
