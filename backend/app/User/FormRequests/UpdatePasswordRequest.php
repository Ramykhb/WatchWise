<?php

namespace App\User\FormRequests;

use App\Support\NewPasswordRules;
use App\User\DTOs\Requests\ChangePasswordData;
use Illuminate\Foundation\Http\FormRequest;

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
            'password' => [...NewPasswordRules::rules(), 'different:current_password'],
        ];
    }

    public function toDto(): ChangePasswordData
    {
        return ChangePasswordData::fromValidated($this->validated());
    }
}
