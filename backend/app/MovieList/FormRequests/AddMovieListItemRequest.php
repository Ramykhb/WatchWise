<?php

namespace App\MovieList\FormRequests;

use App\MovieList\DTOs\Requests\ListFilmData;
use App\Support\MediaType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** POST /api/lists/{list}/items — only the list's owner can add films. */
class AddMovieListItemRequest extends FormRequest
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
            'tmdb_id' => ['required', 'integer', 'min:1'],
            'media_type' => ['sometimes', Rule::in(MediaType::ALL)],
        ];
    }

    /** The title to add: a movie unless `media_type` is "tv" (as on POST /api/watchlist). */
    public function film(): ListFilmData
    {
        return new ListFilmData((int) $this->validated('tmdb_id'), $this->validated('media_type') ?? MediaType::MOVIE);
    }
}
