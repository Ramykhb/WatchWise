<?php

namespace App\Admin\FormRequests;

use App\Movie\DTOs\Requests\LocalMovieQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AdminMovieIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('admin.movies');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.LocalMovieQuery::MAX_PER_PAGE],
        ];
    }

    public function toDto(): LocalMovieQuery
    {
        return LocalMovieQuery::fromValidated($this->validated());
    }
}
