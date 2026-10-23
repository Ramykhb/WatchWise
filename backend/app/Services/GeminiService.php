<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiService
{
    public function ask(string $prompt): array
    {
        $model = config('services.gemini.model');

        $response = Http::timeout(60)
            ->retry(3, 2000, throw: false)
            ->withHeaders(['x-goog-api-key' => config('services.gemini.key')])
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                'contents' => [
                    ['parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Gemini request failed (' . $response->status() . '): ' . $response->body()
            );
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        $data = json_decode($text ?? '', true);

        if (! is_array($data)) {
            throw new RuntimeException('Gemini returned invalid JSON');
        }

        return $data;
    }
}