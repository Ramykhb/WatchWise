<?php

namespace App\Tmdb\Support;

use App\Tmdb\Exceptions\TmdbNotFound;
use App\Tmdb\Exceptions\TmdbUnavailable;
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
    public function get(string $path, array $query = []): Response
    {
        try {
            return Http::acceptJson()
                ->timeout(10)
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
