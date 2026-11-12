<?php

namespace App\Auth\FormRequests;

use App\Auth\DTOs\Requests\RegisterData;
use App\Support\DisplayName;
use App\Support\NewPasswordRules;
use App\Support\NormalizesEmailInput;
use App\Support\UniqueEmail;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    use NormalizesEmailInput;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * The name is trimmed by the global TrimStrings middleware first, so a name of only spaces is missing.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:255', new DisplayName],
            'email' => ['required', 'string', 'email:rfc', 'max:255', new UniqueEmail],
            'password' => NewPasswordRules::rules(),
        ];
    }

    public function toDto(): RegisterData
    {
        return RegisterData::fromValidated($this->validated());
    }
}
