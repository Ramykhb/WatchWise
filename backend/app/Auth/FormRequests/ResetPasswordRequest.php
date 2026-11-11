<?php

namespace App\Auth\FormRequests;

use App\Auth\DTOs\Requests\ResetPasswordData;
use App\Support\NewPasswordRules;
use App\Support\NormalizesEmailInput;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    use NormalizesEmailInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'token' => ['required', 'string', 'max:255'],
            'password' => NewPasswordRules::rules(),
        ];
    }

    public function toDto(): ResetPasswordData
    {
        return ResetPasswordData::fromValidated($this->validated());
    }
}
