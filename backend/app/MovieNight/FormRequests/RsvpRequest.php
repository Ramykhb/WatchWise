<?php

namespace App\MovieNight\FormRequests;

use App\MovieNight\DTOs\Requests\RsvpData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RsvpRequest extends FormRequest
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
            'status' => ['required', Rule::in(RsvpData::STATUSES)],
        ];
    }

    public function toDto(): RsvpData
    {
        return RsvpData::fromValidated($this->validated());
    }
}
