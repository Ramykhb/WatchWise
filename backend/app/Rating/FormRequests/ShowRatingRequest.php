<?php

namespace App\Rating\FormRequests;

use App\Support\MediaType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** GET /api/ratings/{tmdbId}?type=tv — `type` defaults to movie. */
class ShowRatingRequest extends FormRequest
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
            'type' => ['sometimes', Rule::in(MediaType::ALL)],
        ];
    }

    public function mediaType(): string
    {
        return $this->validated('type') ?? MediaType::MOVIE;
    }
}
