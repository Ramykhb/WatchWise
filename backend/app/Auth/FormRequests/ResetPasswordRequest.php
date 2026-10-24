<?php

namespace App\Auth\FormRequests;

use App\Auth\DTOs\Requests\ResetPasswordData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ];
    }

    public function toDto(): ResetPasswordData
    {
        return ResetPasswordData::fromValidated($this->validated());
    }
}
