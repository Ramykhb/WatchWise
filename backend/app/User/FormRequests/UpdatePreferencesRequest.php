<?php

namespace App\User\FormRequests;

use App\User\DTOs\Requests\UpdatePreferencesData;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePreferencesRequest extends FormRequest
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
            'favorite_genres' => ['sometimes', 'array', 'max:10'],
            'favorite_genres.*' => ['string', 'max:40', 'distinct'],
            'favorite_actor_ids' => ['sometimes', 'array', 'max:5'],
            'favorite_actor_ids.*' => ['integer', 'min:1', 'max:2147483647', 'distinct'],
            'favorite_director_ids' => ['sometimes', 'array', 'max:5'],
            'favorite_director_ids.*' => ['integer', 'min:1', 'max:2147483647', 'distinct'],
            'privacy' => ['sometimes', 'array'],
            'privacy.*' => ['boolean'],
            'integrations' => ['sometimes', 'array'],
            'integrations.*' => ['boolean'],
            'notifications' => ['sometimes', 'array'],
            'notifications.*' => ['boolean'],
        ];
    }

    public function toDto(): UpdatePreferencesData
    {
        return UpdatePreferencesData::fromValidated($this->validated());
    }
}
