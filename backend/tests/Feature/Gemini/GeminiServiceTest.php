<?php

namespace Tests\Feature\Gemini;

use App\Gemini\Exceptions\GeminiUnavailable;
use App\Gemini\InterfaceServices\GeminiServiceInterface;
use App\Gemini\Services\GeminiService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class GeminiServiceTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function geminiAnswer(string $text): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => $text]]]]]];
    }

    public function test_interface_resolves_to_the_gemini_service(): void
    {
        $this->assertInstanceOf(GeminiService::class, app(GeminiServiceInterface::class));
    }

    public function test_it_sends_the_expected_request(): void
    {
        config(['services.gemini.model' => 'test-model', 'services.gemini.key' => 'test-key']);
        Http::fake(['*' => Http::response($this->geminiAnswer('{"ok":true}'))]);

        app(GeminiServiceInterface::class)->ask('hello prompt');

        Http::assertSent(function (Request $request): bool {
            return str_contains($request->url(), 'test-model:generateContent')
                && $request->hasHeader('x-goog-api-key', 'test-key')
                && $request['contents'][0]['parts'][0]['text'] === 'hello prompt'
                && $request['generationConfig']['responseMimeType'] === 'application/json';
        });
    }

    public function test_it_returns_the_decoded_answer(): void
    {
        Http::fake(['*' => Http::response($this->geminiAnswer('{"titles":["A","B"]}'))]);

        $this->assertSame(['titles' => ['A', 'B']], app(GeminiServiceInterface::class)->ask('x'));
    }

    public function test_a_failed_response_throws_without_leaking_the_body(): void
    {
        Sleep::fake();
        Http::fake(['*' => Http::response('SECRET-BODY', 500)]);

        try {
            app(GeminiServiceInterface::class)->ask('x');
            $this->fail('Expected GeminiUnavailable.');
        } catch (GeminiUnavailable $e) {
            $this->assertStringContainsString('500', $e->getMessage());
            $this->assertStringNotContainsString('SECRET-BODY', $e->getMessage());
        }
    }

    public function test_a_connection_error_throws_without_leaking_the_exception_message(): void
    {
        Sleep::fake();
        Http::fake(fn () => throw new ConnectionException('boom http://x?key=SECRET'));

        try {
            app(GeminiServiceInterface::class)->ask('x');
            $this->fail('Expected GeminiUnavailable.');
        } catch (GeminiUnavailable $e) {
            $this->assertStringNotContainsString('SECRET', $e->getMessage());
        }
    }

    public function test_invalid_json_text_throws(): void
    {
        Http::fake(['*' => Http::response($this->geminiAnswer('not json'))]);

        $this->expectException(GeminiUnavailable::class);
        $this->expectExceptionMessage('Gemini returned invalid JSON');

        app(GeminiServiceInterface::class)->ask('x');
    }

    public function test_a_missing_text_throws(): void
    {
        Http::fake(['*' => Http::response(['candidates' => []])]);

        $this->expectException(GeminiUnavailable::class);

        app(GeminiServiceInterface::class)->ask('x');
    }
}
