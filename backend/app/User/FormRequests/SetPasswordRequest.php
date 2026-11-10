<?php

namespace App\User\FormRequests;

use App\User\DTOs\Requests\SetPasswordData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class SetPasswordRequest extends FormRequest
{
    /** Any signed-in member may ask; AccountService refuses accounts that already have a password (409). */
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
            // bcrypt ignores everything after a NUL byte, so a new password may not contain one.
            'password' => ['required', 'string', 'not_regex:/\x00/', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    public function toDto(): SetPasswordData
    {
        return SetPasswordData::fromValidated($this->validated());
    }
}
