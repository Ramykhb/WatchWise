<?php

namespace App\Cinemas\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NearbyCinemaController
{
    public function index(Request $request): JsonResponse
    {
        $coordinates = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $apiKey = config('services.geoapify.key');
        if (! $apiKey) {
            return response()->json([
                'message' => 'Add GEOAPIFY_API_KEY to backend/.env to enable nearby cinema search.',
            ], 503);
        }

        $latitude = (float) $coordinates['latitude'];
        $longitude = (float) $coordinates['longitude'];

        try {
            $response = Http::acceptJson()
                ->withHeaders(['x-api-key' => $apiKey])
                ->connectTimeout(5)
                ->timeout(15)
                ->get('https://api.geoapify.com/v2/places', [
                    'categories' => 'entertainment.cinema',
                    'filter' => sprintf('circle:%.7F,%.7F,30000', $longitude, $latitude),
                    'bias' => sprintf('proximity:%.7F,%.7F', $longitude, $latitude),
                    'limit' => 50,
                    'lang' => 'en',
                ]);

            if (! $response->successful()) {
                Log::warning('Geoapify cinema search returned an unsuccessful response.', [
                    'status' => $response->status(),
                    'body' => str($response->body())->limit(500)->toString(),
                ]);

                return response()->json([
                    'message' => 'Geoapify could not complete the nearby cinema search. Check the API key and try again.',
                ], 502);
            }

            $data = $response->json();
            if (! is_array($data) || ! isset($data['features']) || ! is_array($data['features'])) {
                Log::warning('Geoapify returned an invalid cinema search response.');

                return response()->json([
                    'message' => 'Geoapify returned an unexpected cinema search response.',
                ], 502);
            }

            return response()->json(['features' => $data['features']]);
        } catch (ConnectionException $exception) {
            Log::warning('Geoapify cinema search could not be reached.', ['exception' => $exception->getMessage()]);

            return response()->json([
                'message' => 'Geoapify could not be reached. Please try again shortly.',
            ], 503);
        }
    }
}
