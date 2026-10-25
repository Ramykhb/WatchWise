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
            'favorite_genres.*' => ['string', 'max:40'],
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
