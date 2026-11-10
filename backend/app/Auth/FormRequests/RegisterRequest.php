<?php

namespace App\Auth\FormRequests;

use App\Auth\DTOs\Requests\RegisterData;
use App\Support\NormalizesEmailInput;
use App\Support\UniqueEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    use NormalizesEmailInput;

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
            'email' => ['required', 'string', 'email:rfc', 'max:255', new UniqueEmail],
            // bcrypt ignores everything after a NUL byte, so a new password may not contain one.
            'password' => ['required', 'string', 'not_regex:/\x00/', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    public function toDto(): RegisterData
    {
        return RegisterData::fromValidated($this->validated());
    }
}
