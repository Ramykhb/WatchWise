<?php

namespace App\User\FormRequests;

use App\Support\SocialPlaceholderPassword;
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
            // Optional only for accounts that never chose a password; AccountService enforces it for the rest.
            'current_password' => ['nullable', 'string'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers(), SocialPlaceholderPassword::rule()],
        ];
    }

    public function toDto(): ChangePasswordData
    {
        return ChangePasswordData::fromValidated($this->validated());
    }
}
