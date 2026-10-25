<?php

namespace App\Groq\Services;

use App\Groq\Exceptions\GroqUnavailable;
use App\Groq\InterfaceServices\GroqChatServiceInterface;
use App\Groq\InterfaceServices\GroqServiceInterface;
use App\Groq\InterfaceServices\GroqStructuredOutputInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class GroqService implements GroqChatServiceInterface, GroqServiceInterface, GroqStructuredOutputInterface
{
    /**
     * @return array<string, mixed>
     *
     * @throws GroqUnavailable
     */
    public function ask(string $prompt): array
    {
        return $this->askWithSchema($prompt, 'weather_mood', [
            'type' => 'object',
            'properties' => [
                'mood' => ['type' => 'string'],
                'description' => ['type' => 'string'],
            ],
            'required' => ['mood', 'description'],
            'additionalProperties' => false,
        ], 512);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     *
     * @throws GroqUnavailable
     */
    public function askWithSchema(string $prompt, string $name, array $schema, int $maxCompletionTokens = 1024): array
    {
        [$key, $url, $model] = $this->configuration();

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
                            'name' => $name,
                            'strict' => true,
                            'schema' => $schema,
                        ],
                    ],
                    'reasoning_effort' => 'low',
                    'include_reasoning' => false,
                    'max_completion_tokens' => $maxCompletionTokens,
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

    public function streamChat(array $messages, callable $onDelta): string
    {
        [$key, $url, $model] = $this->configuration();

        try {
            $response = Http::accept('text/event-stream')
                ->asJson()
                ->withToken($key)
                ->connectTimeout(5)
                ->timeout(60)
                ->withOptions(['stream' => true, 'read_timeout' => 30])
                ->post("{$url}/chat/completions", [
                    'model' => $model,
                    'messages' => $messages,
                    'stream' => true,
                    'store' => false,
                    'reasoning_effort' => 'low',
                    'include_reasoning' => false,
                    'max_completion_tokens' => 2048,
                ]);
        } catch (ConnectionException) {
            throw new GroqUnavailable('Groq chat request failed: connection error.');
        }

        if ($response->failed()) {
            throw new GroqUnavailable("Groq chat request failed ({$response->status()}).");
        }

        $body = $response->toPsrResponse()->getBody();
        $buffer = '';
        $reply = '';
        $started = microtime(true);

        try {
            while (true) {
                if (microtime(true) - $started > 60) {
                    throw new GroqUnavailable('Groq chat stream timed out.');
                }
                try {
                    if ($body->eof()) {
                        break;
                    }
                    $buffer .= $body->read(1024);
                } catch (\RuntimeException) {
                    throw new GroqUnavailable('Groq chat stream was interrupted.');
                }

                while (($end = strpos($buffer, "\n")) !== false) {
                    $line = rtrim(substr($buffer, 0, $end), "\r");
                    $buffer = substr($buffer, $end + 1);
                    if (! str_starts_with($line, 'data:')) {
                        continue;
                    }
                    $data = trim(substr($line, 5));
                    if ($data === '[DONE]') {
                        if (trim($reply) === '') {
                            throw new GroqUnavailable('Groq returned an empty chat reply.');
                        }

                        return $reply;
                    }

                    $chunk = json_decode($data, true);
                    if (! is_array($chunk) || isset($chunk['error'])) {
                        throw new GroqUnavailable('Groq returned an invalid chat stream.');
                    }
                    if (($chunk['choices'][0]['finish_reason'] ?? null) === 'length') {
                        throw new GroqUnavailable('Groq chat exceeded the output budget.');
                    }
                    $delta = $chunk['choices'][0]['delta']['content'] ?? null;
                    if (is_string($delta) && $delta !== '') {
                        $reply .= $delta;
                        if (mb_strlen($reply) > 8000) {
                            throw new GroqUnavailable('Groq chat exceeded the reply limit.');
                        }
                        $onDelta($delta);
                    }
                }
                if (strlen($buffer) > 65536) {
                    throw new GroqUnavailable('Groq returned an invalid chat stream.');
                }
            }
        } finally {
            $body->close();
        }

        throw new GroqUnavailable('Groq chat stream ended before completion.');
    }

    /** @return array{string, string, string} */
    private function configuration(): array
    {
        $key = config('services.groq.key');
        $url = rtrim((string) config('services.groq.url'), '/');
        $model = config('services.groq.model');

        if (! is_string($key) || $key === '' || ! is_string($model) || $model === '' || $url === '') {
            throw new GroqUnavailable('Groq is not configured.');
        }

        return [$key, $url, $model];
    }
}
