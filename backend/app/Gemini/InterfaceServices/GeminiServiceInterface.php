<?php

namespace App\Gemini\InterfaceServices;

use App\Gemini\Exceptions\GeminiUnavailable;

interface GeminiServiceInterface
{
    /**
     * Send a prompt to Gemini and return the JSON object it answers with.
     *
     * @return array<string, mixed>
     *
     * @throws GeminiUnavailable
     */
    public function ask(string $prompt): array;
}
