<?php

namespace App\Groq\Services;

use App\Groq\Exceptions\GroqUnavailable;
use App\Groq\InterfaceServices\GroqServiceInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class GroqService implements GroqServiceInterface
{
    /**
     * @return array<string, mixed>
     *
     * @throws GroqUnavailable
     */
    public function ask(string $prompt): array
    {
        $key = config('services.groq.key');
        $url = rtrim((string) config('services.groq.url'), '/');
        $model = config('services.groq.model');

        if (! is_string($key) || $key === '' || ! is_string($model) || $model === '' || $url === '') {
            throw new GroqUnavailable('Groq is not configured.');
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withToken($key)
                ->connectTimeout(5)
                ->timeout(30)
                ->post("{$url}/chat/completions", [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => [
                            'name' => 'weather_mood',
                            'strict' => true,
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'mood' => ['type' => 'string'],
                                    'description' => ['type' => 'string'],
                                ],
                                'required' => ['mood', 'description'],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                    'reasoning_effort' => 'low',
                    'include_reasoning' => false,
                    'max_completion_tokens' => 512,
                ]);
        } catch (ConnectionException) {
            throw new GroqUnavailable('Groq request failed: connection error.');
        }

        if ($response->failed()) {
            throw new GroqUnavailable("Groq request failed ({$response->status()}).");
        }

        $content = $response->json('choices.0.message.content');
        $data = is_string($content) ? json_decode($content, true) : null;

        if (! is_array($data)) {
            throw new GroqUnavailable('Groq returned invalid JSON.');
        }

        return $data;
    }
}
