<?php

namespace App\MovieList\FormRequests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

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
        ];
    }

    public function tmdbId(): int
    {
        return (int) $this->validated('tmdb_id');
    }
}
