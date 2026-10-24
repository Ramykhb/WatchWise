<?php

namespace App\Tmdb\FormRequests;

use App\Tmdb\DTOs\Requests\MovieSearchQuery;
use Illuminate\Foundation\Http\FormRequest;

class MovieSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'between:1,500'],
            'year' => ['sometimes', 'integer', 'between:1874,2100'],
        ];
    }

    public function toDto(): MovieSearchQuery
    {
        return MovieSearchQuery::fromValidated($this->validated());
    }
}
