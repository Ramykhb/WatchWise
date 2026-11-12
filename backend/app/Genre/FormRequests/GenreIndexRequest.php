<?php

namespace App\Genre\FormRequests;

use App\Genre\DTOs\Requests\GenreListQuery;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The genre list is public, so there is nothing to authorize. Any `type` other than `tv` means movie, as it
 * always has; only `include_hidden` is validated.
 */
class GenreIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'include_hidden' => ['sometimes', 'boolean'],
        ];
    }

    public function toDto(): GenreListQuery
    {
        return new GenreListQuery(
            $this->query('type') === 'tv' ? 'tv' : 'movie',
            $this->boolean('include_hidden'),
        );
    }
}
