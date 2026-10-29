<?php

namespace App\MovieList\FormRequests;

use App\Support\MediaType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** DELETE /api/lists/{list}/items/{tmdbId}?type=movie|tv — only the list's owner can remove films. */
class RemoveMovieListItemRequest extends FormRequest
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
            'type' => ['sometimes', Rule::in(MediaType::ALL)],
        ];
    }

    /** Which title the id names: a movie unless `?type=tv` (as on the ratings endpoints). */
    public function mediaType(): string
    {
        return $this->validated('type') ?? MediaType::MOVIE;
    }
}
