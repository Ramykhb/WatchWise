<?php

namespace App\Cinema\Services;

use App\Cinema\DTOs\Requests\NearbyCinemasQuery;
use App\Cinema\DTOs\Responses\CinemaResponse;
use App\Cinema\Exceptions\CinemaSearchUnavailable;
use App\Cinema\InterfaceServices\CinemaServiceInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CinemaService implements CinemaServiceInterface
{
    private const SEARCH_RADIUS_METERS = 30000;

    private const FIELD_MASK = 'places.id,places.displayName,places.formattedAddress,places.location,places.websiteUri';

    public function nearby(NearbyCinemasQuery $query): array
    {
        $apiKey = config('services.google_places.key');
        if (! is_string($apiKey) || $apiKey === '') {
            throw new CinemaSearchUnavailable(503, 'Google Places is not configured. Set GOOGLE_PLACES_API_KEY in backend/.env.');
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders([
                    'X-Goog-Api-Key' => $apiKey,
                    'X-Goog-FieldMask' => self::FIELD_MASK,
                ])
                ->connectTimeout(5)
                ->timeout(15)
                ->post('https://places.googleapis.com/v1/places:searchNearby', [
                    'includedPrimaryTypes' => ['movie_theater'],
                    'maxResultCount' => 20,
                    'rankPreference' => 'DISTANCE',
                    'locationRestriction' => [
                        'circle' => [
                            'center' => ['latitude' => $query->latitude, 'longitude' => $query->longitude],
                            'radius' => self::SEARCH_RADIUS_METERS,
                        ],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Google Places cinema search could not be reached.', ['exception' => $exception->getMessage()]);
            throw new CinemaSearchUnavailable(503, 'Google Places could not be reached. Please try again shortly.');
        }

        if (! $response->successful()) {
            Log::warning('Google Places cinema search returned an unsuccessful response.', [
                'status' => $response->status(),
                'body' => str($response->body())->limit(500)->toString(),
            ]);
            throw new CinemaSearchUnavailable(502, 'Google Places could not complete the nearby cinema search.');
        }

        $places = $response->json('places', []);
        if (! is_array($places)) {
            Log::warning('Google Places returned an invalid cinema search response.');
            throw new CinemaSearchUnavailable(502, 'Google Places returned an unexpected response.');
        }

        $cinemas = [];
        foreach ($places as $place) {
            if (! is_array($place)) {
                continue;
            }

            $location = $place['location'] ?? null;
            $latitude = $location['latitude'] ?? null;
            $longitude = $location['longitude'] ?? null;
            $name = $place['displayName']['text'] ?? null;
            if (! is_string($name) || ! is_numeric($latitude) || ! is_numeric($longitude) || ! is_string($place['id'] ?? null)) {
                continue;
            }

            $cinemas[] = new CinemaResponse(
                $place['id'],
                $name,
                is_string($place['formattedAddress'] ?? null) ? $place['formattedAddress'] : 'Address unavailable',
                (float) $latitude,
                (float) $longitude,
                (int) round($this->distanceMeters($query->latitude, $query->longitude, (float) $latitude, (float) $longitude)),
                is_string($place['websiteUri'] ?? null) ? $place['websiteUri'] : null,
            );
        }

        usort($cinemas, fn (CinemaResponse $first, CinemaResponse $second): int => $first->distanceMeters <=> $second->distanceMeters);

        return $cinemas;
    }

    private function distanceMeters(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): float
    {
        $toRadians = fn (float $degrees): float => deg2rad($degrees);
        $latitudeDelta = $toRadians($latitudeB - $latitudeA);
        $longitudeDelta = $toRadians($longitudeB - $longitudeA);
        $a = sin($latitudeDelta / 2) ** 2
            + cos($toRadians($latitudeA)) * cos($toRadians($latitudeB)) * sin($longitudeDelta / 2) ** 2;

        return 6_371_000 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
