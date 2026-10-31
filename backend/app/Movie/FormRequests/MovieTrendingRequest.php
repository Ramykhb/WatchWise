<?php

namespace App\Movie\FormRequests;

use App\Movie\DTOs\Requests\MovieTrendingQuery;
use App\Support\MediaType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MovieTrendingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['type' => ['sometimes', 'string', Rule::in(MediaType::ALL)]];
    }

    public function toDto(): MovieTrendingQuery
    {
        return MovieTrendingQuery::fromValidated($this->validated());
    }
}
