<?php

namespace App\MovieNight\FormRequests;

use App\Models\MovieNight;
use App\MovieNight\DTOs\Requests\CreateMovieNightData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreMovieNightRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', MovieNight::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:80'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_link' => ['nullable', 'url', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
            'invitees' => ['sometimes', 'array', 'max:30'],
            'invitees.*' => ['string', 'distinct', Rule::exists('users', 'username')],
            'tmdb_ids' => ['sometimes', 'array', 'max:10'],
            'tmdb_ids.*' => ['integer', 'min:1', 'distinct'],
        ];
    }

    public function toDto(): CreateMovieNightData
    {
        return CreateMovieNightData::fromValidated($this->validated());
    }
}
