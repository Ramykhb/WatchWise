<?php

namespace App\Tmdb\FormRequests;

use App\Tmdb\DTOs\Requests\MovieListQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MovieListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'list' => ['sometimes', 'string', Rule::in(MovieListQuery::LISTS)],
            'page' => ['sometimes', 'integer', 'between:1,500'],
        ];
    }

    public function toDto(): MovieListQuery
    {
        return MovieListQuery::fromValidated($this->validated());
    }
}
