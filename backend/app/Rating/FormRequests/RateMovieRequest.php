<?php

namespace App\Rating\FormRequests;

use App\Support\MediaType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** PUT /api/ratings/{tmdbId} — the signed-in member rates a movie or (`type: tv`) a TV show; the route is behind auth.jwt. */
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
            'type' => ['sometimes', Rule::in(MediaType::ALL)],
        ];
    }

    public function mediaType(): string
    {
        return $this->validated('type') ?? MediaType::MOVIE;
    }

    public function rating(): int
    {
        return (int) $this->validated('rating');
    }
}
