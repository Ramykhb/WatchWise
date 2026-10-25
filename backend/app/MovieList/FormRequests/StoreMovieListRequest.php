<?php

namespace App\MovieList\FormRequests;

use App\Models\MovieList;
use App\MovieList\DTOs\Requests\CreateMovieListData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreMovieListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', MovieList::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:80'],
            'description' => ['sometimes', 'nullable', 'string', 'max:280'],
            'is_public' => ['sometimes', 'boolean'],
            'tmdb_ids' => ['sometimes', 'array', 'max:500'],
            'tmdb_ids.*' => ['integer', 'min:1', 'distinct'],
        ];
    }

    public function toDto(): CreateMovieListData
    {
        return CreateMovieListData::fromValidated($this->validated());
    }
}
