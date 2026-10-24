<?php

namespace App\MovieNight\FormRequests;

use App\MovieNight\DTOs\Requests\UpdateMovieNightData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateMovieNightRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('night'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:80'],
            'scheduled_at' => ['sometimes', 'date'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meeting_link' => ['sometimes', 'nullable', 'url', 'max:255'],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
            'status' => ['sometimes', Rule::in(['pending', 'confirmed', 'cancelled'])],
            // The winner has to be on this night's shortlist.
            'winning_tmdb_id' => ['sometimes', 'nullable', 'integer', Rule::exists('movie_night_candidates', 'tmdb_id')->where('movie_night_id', $this->route('night')->id)],
        ];
    }

    public function toDto(): UpdateMovieNightData
    {
        return UpdateMovieNightData::fromValidated($this->validated());
    }
}
