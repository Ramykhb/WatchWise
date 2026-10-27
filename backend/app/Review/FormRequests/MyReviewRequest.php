<?php

namespace App\Review\FormRequests;

use App\Support\MediaType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** DELETE /api/reviews/mine?tmdb_id=&type= — names the title whose review the member removes. */
class MyReviewRequest extends FormRequest
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
            'tmdb_id' => ['required', 'integer', 'min:1'],
            'type' => ['sometimes', Rule::in(MediaType::ALL)],
        ];
    }

    public function tmdbId(): int
    {
        return (int) $this->validated('tmdb_id');
    }

    public function mediaType(): string
    {
        return $this->validated('type') ?? MediaType::MOVIE;
    }
}
