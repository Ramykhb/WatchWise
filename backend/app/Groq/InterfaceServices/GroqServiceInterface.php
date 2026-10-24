<?php

namespace App\Groq\InterfaceServices;

use App\Groq\Exceptions\GroqUnavailable;

interface GroqServiceInterface
{
    /**
     * Ask Groq for a JSON object.
     *
     * @return array<string, mixed>
     *
     * @throws GroqUnavailable
     */
    public function ask(string $prompt): array;
}
