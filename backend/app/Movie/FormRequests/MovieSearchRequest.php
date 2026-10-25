<?php

namespace App\Movie\FormRequests;

use App\Movie\DTOs\Requests\MovieSearchQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'type' => ['sometimes', 'string', Rule::in(['movie', 'tv', 'all'])],
        ];
    }

    public function toDto(): MovieSearchQuery
    {
        return MovieSearchQuery::fromValidated($this->validated());
    }
}
