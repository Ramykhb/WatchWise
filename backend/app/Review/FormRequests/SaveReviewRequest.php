<?php

namespace App\Review\FormRequests;

use App\Review\DTOs\Requests\SaveReviewData;
use App\Support\MediaType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** PUT /api/reviews/mine — the signed-in member writes or edits their review of a title. */
class SaveReviewRequest extends FormRequest
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
            'content' => ['required', 'string', 'max:'.SaveReviewData::MAX_LENGTH, 'not_regex:/^\s*$/'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'has_spoilers' => ['sometimes', 'boolean'],
        ];
    }

    public function toDto(): SaveReviewData
    {
        return SaveReviewData::fromValidated($this->validated());
    }
}
