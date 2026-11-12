<?php

namespace App\Admin\FormRequests;

use App\Rating\DTOs\Requests\RatingQuery;
use App\Rating\Support\Stars;
use App\Support\MediaType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AdminRatingIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('admin.ratings');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tmdb_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'media_type' => ['sometimes', 'nullable', Rule::in(MediaType::ALL)],
            'user_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'rating' => ['sometimes', 'nullable', 'integer', 'between:'.Stars::MIN.','.Stars::MAX],
            'q' => ['sometimes', 'nullable', 'string', 'max:200'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.RatingQuery::MAX_PER_PAGE],
        ];
    }

    public function toDto(): RatingQuery
    {
        return RatingQuery::fromValidated($this->validated());
    }
}
