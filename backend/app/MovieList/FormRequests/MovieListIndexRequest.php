<?php

namespace App\MovieList\FormRequests;

use App\MovieList\DTOs\Requests\MovieListSearchQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MovieListIndexRequest extends FormRequest
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
            'scope' => ['sometimes', 'string', Rule::in(MovieListSearchQuery::SCOPES)],
            'q' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function toDto(): MovieListSearchQuery
    {
        return MovieListSearchQuery::fromValidated($this->validated());
    }
}
