<?php

namespace App\MovieList\FormRequests;

use App\Models\MovieList;
use App\MovieList\DTOs\Requests\ListFilmData;
use App\MovieList\DTOs\Requests\MovieListSearchQuery;
use App\Support\MediaType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MovieListIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', MovieList::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scope' => ['sometimes', 'string', Rule::in(MovieListSearchQuery::SCOPES)],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'contains' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.ListFilmData::MAX_ID],
            'contains_type' => ['sometimes', Rule::in(MediaType::ALL)],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.MovieListSearchQuery::MAX_PER_PAGE],
        ];
    }

    public function toDto(): MovieListSearchQuery
    {
        return MovieListSearchQuery::fromValidated($this->validated());
    }
}
