<?php

namespace App\MovieList\FormRequests;

use App\Models\MovieList;
use App\MovieList\DTOs\Requests\CreateMovieListData;
use App\MovieList\DTOs\Requests\ListFilmData;
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
            // Movies as TMDB ids (550) and TV shows as "tv-1399", in list order.
            'tmdb_ids' => ['sometimes', 'array', 'max:500'],
            'tmdb_ids.*' => ['distinct', 'regex:'.ListFilmData::KEY_PATTERN],
        ];
    }

    public function toDto(): CreateMovieListData
    {
        return CreateMovieListData::fromValidated($this->validated());
    }
}
