<?php

namespace App\MovieNight\FormRequests;

use App\MovieNight\DTOs\Requests\VoteData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class VoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('view', $this->route('night'));
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

    public function toDto(): VoteData
    {
        return VoteData::fromValidated($this->validated());
    }
}
