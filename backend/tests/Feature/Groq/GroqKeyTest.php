<?php

namespace Tests\Feature\Groq;

use App\Groq\Exceptions\GroqUnavailable;
use App\Groq\InterfaceServices\GroqStructuredOutputInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class GroqKeyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.groq.key' => 'primary-test-key',
            'services.groq.watched_key' => 'second-test-key',
            'services.groq.model' => 'test-model',
            'services.groq.url' => 'https://api.groq.com/openai/v1',
        ]);
        Http::preventStrayRequests();
    }

    public function test_watched_requests_use_key_two_and_other_requests_keep_the_primary_key(): void
    {
        Http::fake(['api.groq.com/*' => Http::response([
            'choices' => [['message' => ['content' => '{"movies":[]}']]],
        ])]);
        $client = app(GroqStructuredOutputInterface::class);
        $client->askWithSchema('watched prompt', 'watched', [], keyProfile: 'watched');
        $client->askWithSchema('other prompt', 'other', []);

        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request['messages'][0]['content'] === 'watched prompt'
            && $request->hasHeader('Authorization', 'Bearer second-test-key'));
        Http::assertSent(fn ($request): bool => $request['messages'][0]['content'] === 'other prompt'
            && $request->hasHeader('Authorization', 'Bearer primary-test-key'));
    }

    public function test_it_retries_a_watched_request_once_after_a_connection_failure(): void
    {
        Sleep::fake();
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            if (++$attempts === 1) {
                throw new ConnectionException('Temporary connection failure');
            }

            return Http::response(['choices' => [['message' => ['content' => '{"movies":[]}']]]]);
        });

        $result = app(GroqStructuredOutputInterface::class)->askWithSchema('prompt', 'watched', [], keyProfile: 'watched');

        $this->assertSame(['movies' => []], $result);
        $this->assertSame(2, $attempts);
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer second-test-key'));
    }

    public function test_it_does_not_retry_rate_limited_requests(): void
    {
        Http::fake(['api.groq.com/*' => Http::response([], 429)]);
        try {
            app(GroqStructuredOutputInterface::class)->askWithSchema('prompt', 'watched', [], keyProfile: 'watched');
            $this->fail('Expected a rate-limit error.');
        } catch (GroqUnavailable $error) {
            $this->assertSame('Groq request failed (429).', $error->getMessage());
        }
        Http::assertSentCount(1);
    }

    public function test_a_missing_second_key_does_not_fall_back_to_the_primary_account(): void
    {
        config(['services.groq.watched_key' => null]);

        try {
            app(GroqStructuredOutputInterface::class)->askWithSchema('prompt', 'watched', [], keyProfile: 'watched');
            $this->fail('Expected a missing-key error.');
        } catch (GroqUnavailable $error) {
            $this->assertSame('Groq is not configured.', $error->getMessage());
        }
        Http::assertNothingSent();
    }
}
