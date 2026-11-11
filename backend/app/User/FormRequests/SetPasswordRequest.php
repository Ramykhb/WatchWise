<?php

namespace App\User\FormRequests;

use App\Support\NewPasswordRules;
use App\User\DTOs\Requests\SetPasswordData;
use Illuminate\Foundation\Http\FormRequest;

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
            'password' => NewPasswordRules::rules(),
        ];
    }

    public function toDto(): SetPasswordData
    {
        return SetPasswordData::fromValidated($this->validated());
    }
}
