<?php

namespace App\Tmdb\FormRequests;

use App\Tmdb\DTOs\Requests\MovieDiscoverQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MovieDiscoverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'genres' => ['sometimes', 'string', 'regex:/^\d+(,\d+)*$/'],
            'year_from' => ['sometimes', 'integer', 'between:1874,2100'],
            'year_to' => ['sometimes', 'integer', 'between:1874,2100', Rule::when($this->filled('year_from'), 'gte:year_from')],
            'min_rating' => ['sometimes', 'numeric', 'between:0,5'],
            'runtime_min' => ['sometimes', 'integer', 'min:0'],
            'runtime_max' => ['sometimes', 'integer', 'min:1', Rule::when($this->filled('runtime_min'), 'gte:runtime_min')],
            'language' => ['sometimes', 'string', 'regex:/^[a-z]{2}$/'],
            'sort' => ['sometimes', 'string', Rule::in(MovieDiscoverQuery::SORTS)],
            'page' => ['sometimes', 'integer', 'between:1,500'],
        ];
    }

    public function toDto(): MovieDiscoverQuery
    {
        return MovieDiscoverQuery::fromValidated($this->validated());
    }
}
