<?php

namespace Tests\Feature\Weather;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherMoodTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'condition' => 'Rain',
            'temperature' => 12.5,
            'humidity' => 80,
            'wind' => 10,
            'precipitation' => 2,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'k', 'services.gemini.model' => 'm']);
    }

    public function test_it_returns_the_weather_mood(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode(['mood' => 'Cozy dramas', 'description' => 'Rainy evenings call for slow picks.']),
                    ]]],
                ]],
            ]),
        ]);

        $this->postJson('/api/weather-mood', $this->payload())
            ->assertOk()
            ->assertExactJson([
                'title' => 'WEATHER MOOD',
                'mood' => 'Cozy dramas',
                'description' => 'Rainy evenings call for slow picks.',
            ]);
    }

    public function test_it_validates_the_payload(): void
    {
        $this->postJson('/api/weather-mood', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['condition', 'temperature', 'humidity', 'wind', 'precipitation']);
    }

    public function test_it_returns_502_without_leaking_gemini_errors(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'SECRET_GEMINI_MARKER']], 500),
        ]);

        $response = $this->postJson('/api/weather-mood', $this->payload())
            ->assertStatus(502)
            ->assertExactJson(['error' => 'Could not generate the weather mood']);

        $this->assertStringNotContainsString('SECRET_GEMINI_MARKER', $response->getContent());
    }

    public function test_it_is_throttled_to_ten_requests_a_minute(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => '{"mood":"a","description":"b"}']]]]],
            ]),
        ]);

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/weather-mood', $this->payload())->assertOk();
        }

        $this->postJson('/api/weather-mood', $this->payload())->assertStatus(429);
    }
}
