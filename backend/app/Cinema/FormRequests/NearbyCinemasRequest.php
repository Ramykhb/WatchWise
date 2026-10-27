<?php

namespace App\Cinema\FormRequests;

use App\Cinema\DTOs\Requests\NearbyCinemasQuery;
use Illuminate\Foundation\Http\FormRequest;

class NearbyCinemasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    public function toDto(): NearbyCinemasQuery
    {
        return NearbyCinemasQuery::fromValidated($this->validated());
    }
}
