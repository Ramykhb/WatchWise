<?php

namespace App\User\FormRequests;

use App\Auth\CurrentUser\CurrentUser;
use App\Support\EmailAddress;
use App\Support\NormalizesEmailInput;
use App\Support\UniqueEmail;
use App\User\DTOs\Requests\RequestEmailChangeData;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class RequestEmailChangeRequest extends FormRequest
{
    use NormalizesEmailInput;

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
                    if (is_string($value) && $value === EmailAddress::normalize($user->email)) {
                        $fail('That is already your email address.');
                    }
                },
                new UniqueEmail(exceptUserId: $user->id),
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
