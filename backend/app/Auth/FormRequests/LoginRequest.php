<?php

namespace App\Auth\FormRequests;

use App\Auth\DTOs\Requests\LoginData;
use App\Support\MaxBytes;
use App\Support\NormalizesEmailInput;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    use NormalizesEmailInput;

    /**
     * Far above the 72 bytes bcrypt reads, so passwords chosen before new ones were capped (the old rules had no
     * maximum) still sign in, while a huge body is never regex-checked or hashed.
     */
    public const PASSWORD_MAX_BYTES = 1024;

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
            'email' => ['required', 'string', 'email', 'max:255'],
            // bcrypt ignores everything after a NUL byte, so no real password contains one.
            'password' => ['bail', 'required', 'string', new MaxBytes(self::PASSWORD_MAX_BYTES), 'not_regex:/\x00/'],
            // Optional; without it the session is remembered, as before the option existed.
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    public function toDto(): LoginData
    {
        return LoginData::fromValidated($this->validated());
    }
}
