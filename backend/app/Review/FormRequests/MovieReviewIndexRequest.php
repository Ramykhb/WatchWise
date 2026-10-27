<?php

namespace App\Review\FormRequests;

use App\Review\DTOs\Requests\MovieReviewQuery;
use App\Support\MediaType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** GET /api/reviews?tmdb_id=&type=&sort=&page= — any signed-in member can read a title's reviews. */
class MovieReviewIndexRequest extends FormRequest
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
            'sort' => ['sometimes', Rule::in(MovieReviewQuery::SORTS)],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function toDto(): MovieReviewQuery
    {
        return MovieReviewQuery::fromValidated($this->validated());
    }
}
