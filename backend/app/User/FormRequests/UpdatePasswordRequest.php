<?php

namespace App\User\FormRequests;

use App\User\DTOs\Requests\ChangePasswordData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
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
            'current_password' => ['required', 'string'],
            // bcrypt ignores everything after a NUL byte, so a new password may not contain one.
            'password' => ['required', 'string', 'not_regex:/\x00/', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ];
    }

    public function toDto(): ChangePasswordData
    {
        return ChangePasswordData::fromValidated($this->validated());
    }
}
