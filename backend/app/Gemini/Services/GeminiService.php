<?php

namespace App\Gemini\Services;

use App\Gemini\Exceptions\GeminiUnavailable;
use App\Gemini\InterfaceServices\GeminiServiceInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class GeminiService implements GeminiServiceInterface
{
    /**
     * @return array<string, mixed>
     *
     * @throws GeminiUnavailable
     */
    public function ask(string $prompt): array
    {
        $model = config('services.gemini.model');
        $url = config('services.gemini.url');

        try {
            $response = Http::timeout(60)
                ->retry(3, 2000, throw: false)
                ->withHeaders(['x-goog-api-key' => config('services.gemini.key')])
                ->post("{$url}/{$model}:generateContent", [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]],
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                    ],
                ]);
        } catch (ConnectionException) {
            throw new GeminiUnavailable('Gemini request failed: connection error');
        }

        if ($response->failed()) {
            throw new GeminiUnavailable("Gemini request failed ({$response->status()})");
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        $data = is_string($text) ? json_decode($text, true) : null;

        if (! is_array($data)) {
            throw new GeminiUnavailable('Gemini returned invalid JSON');
        }

        return $data;
    }
}
