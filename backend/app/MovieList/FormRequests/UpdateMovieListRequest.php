<?php

namespace App\MovieList\FormRequests;

use App\MovieList\DTOs\Requests\UpdateMovieListData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateMovieListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('list'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:80'],
            'description' => ['sometimes', 'nullable', 'string', 'max:280'],
            'is_public' => ['sometimes', 'boolean'],
            'tmdb_ids' => ['sometimes', 'array', 'max:500'],
            'tmdb_ids.*' => ['integer', 'min:1', 'distinct'],
        ];
    }

    public function toDto(): UpdateMovieListData
    {
        return UpdateMovieListData::fromValidated($this->validated());
    }
}
