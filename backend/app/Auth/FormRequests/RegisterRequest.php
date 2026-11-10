<?php

namespace App\Auth\FormRequests;

use App\Auth\DTOs\Requests\RegisterData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            // bcrypt ignores everything after a NUL byte, so a new password may not contain one.
            'password' => ['required', 'string', 'not_regex:/\x00/', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    public function toDto(): RegisterData
    {
        return RegisterData::fromValidated($this->validated());
    }
}
