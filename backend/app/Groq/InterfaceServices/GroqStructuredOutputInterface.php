<?php

namespace App\Groq\InterfaceServices;

use App\Groq\Exceptions\GroqUnavailable;

interface GroqStructuredOutputInterface
{
    /**
     * Ask Groq for a response matching a strict JSON schema.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     *
     * @throws GroqUnavailable
     */
    public function askWithSchema(string $prompt, string $name, array $schema, int $maxCompletionTokens = 1024): array;
}
