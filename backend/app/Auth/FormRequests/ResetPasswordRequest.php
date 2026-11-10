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
            // bcrypt ignores everything after a NUL byte, so a new password may not contain one.
            'password' => ['required', 'string', 'not_regex:/\x00/', 'confirmed', Password::min(8)],
        ];
    }

    public function toDto(): ResetPasswordData
    {
        return ResetPasswordData::fromValidated($this->validated());
    }
}
