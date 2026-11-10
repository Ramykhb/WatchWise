<?php

namespace App\Auth\FormRequests;

use App\Auth\DTOs\Requests\ForgotPasswordData;
use App\Support\NormalizesEmailInput;
use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    use NormalizesEmailInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email', 'max:255']];
    }

    public function toDto(): ForgotPasswordData
    {
        return ForgotPasswordData::fromValidated($this->validated());
    }
}
