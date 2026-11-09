<?php

namespace App\User\FormRequests;

use App\Auth\CurrentUser\CurrentUser;
use App\User\DTOs\Requests\RequestEmailChangeData;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestEmailChangeRequest extends FormRequest
{
    /** Members only change their own address; the service checks the password. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = app(CurrentUser::class)->user();

        return [
            'email' => [
                'required', 'string', 'email:rfc', 'max:255',
                function (string $attribute, mixed $value, Closure $fail) use ($user): void {
                    if (is_string($value) && strcasecmp(trim($value), $user->email) === 0) {
                        $fail('That is already your email address.');
                    }
                },
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            // Optional only for accounts that never chose a password; EmailChangeService enforces it for the rest.
            'current_password' => ['nullable', 'string'],
        ];
    }

    public function toDto(): RequestEmailChangeData
    {
        return RequestEmailChangeData::fromValidated($this->validated());
    }
}
