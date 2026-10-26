<?php

namespace App\Rating\FormRequests;

use Illuminate\Foundation\Http\FormRequest;

/** PUT /api/ratings/{tmdbId} — the signed-in member rates a film; the route is already behind auth.jwt. */
class RateMovieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
        ];
    }

    public function rating(): int
    {
        return (int) $this->validated('rating');
    }
}
