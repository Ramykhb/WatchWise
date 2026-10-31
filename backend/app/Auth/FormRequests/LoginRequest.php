<?php

namespace App\Auth\FormRequests;

use App\Auth\DTOs\Requests\LoginData;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'email' => ['required', 'string', 'email'],
            // bcrypt ignores everything after a NUL byte, so no real password contains one.
            'password' => ['required', 'string', 'not_regex:/\x00/'],
        ];
    }

    public function toDto(): LoginData
    {
        return LoginData::fromValidated($this->validated());
    }
}
