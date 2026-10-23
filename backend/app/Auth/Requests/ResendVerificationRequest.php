<?php

namespace App\Auth\Requests;

use App\Auth\DTOs\Requests\ResendVerificationData;
use Illuminate\Foundation\Http\FormRequest;

class ResendVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email', 'max:255']];
    }

    public function toDto(): ResendVerificationData
    {
        return ResendVerificationData::fromValidated($this->validated());
    }
}
