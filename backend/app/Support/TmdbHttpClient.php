<?php

namespace App\Support;

use App\Support\Exceptions\TmdbNotFound;
use App\Support\Exceptions\TmdbUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class TmdbHttpClient
{
    /**
     * Call a TMDB endpoint with the API key and return the response.
     *
     * @param  array<string, mixed>  $query
     *
     * @throws TmdbNotFound when TMDB answers 404
     * @throws TmdbUnavailable
     */
    public function get(string $path, array $query = [], int $timeout = 10, int $attempts = 1): Response
    {
        try {
            return Http::acceptJson()
                ->connectTimeout(min($timeout, 10))
                ->timeout($timeout)
                ->retry($attempts, 1000, fn (\Exception $error): bool => $error instanceof ConnectionException, throw: false)
                ->get(config('services.tmdb.url').$path, [
                    'api_key' => config('services.tmdb.key'),
                    'language' => 'en-US',
                    ...$query,
                ])
                ->throw();
        } catch (RequestException $exception) {
            if ($exception->response->notFound()) {
                throw new TmdbNotFound($path);
            }

            throw TmdbUnavailable::fromFailure($path, $exception);
        } catch (ConnectionException $exception) {
            throw TmdbUnavailable::fromFailure($path, $exception);
        }
    }
}
