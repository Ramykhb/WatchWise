<?php

namespace App\Admin\FormRequests;

use App\MovieList\DTOs\Requests\ListFilmData;
use App\Support\MediaType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** DELETE /api/admin/lists/{list}/items/{tmdbId}?type=movie|tv — the title is a movie unless `type` says tv. */
class RemoveListItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('admin.lists');
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

    public function toDto(): ListFilmData
    {
        return new ListFilmData((int) $this->route('tmdbId'), $this->validated('type') ?? MediaType::MOVIE);
    }
}
