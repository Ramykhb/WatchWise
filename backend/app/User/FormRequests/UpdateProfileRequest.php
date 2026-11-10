<?php

namespace App\User\FormRequests;

use App\Auth\CurrentUser\CurrentUser;
use App\Models\User;
use App\Support\EmailAddress;
use App\Support\NormalizesEmailInput;
use App\User\DTOs\Requests\UpdateProfileData;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    use NormalizesEmailInput;

    public function authorize(): bool
    {
        return true;
    }

    /** "@Jane" and "jane" are the same handle: it is lowercased before the uniqueness check and stored that way. */
    protected function prepareForValidation(): void
    {
        $this->normalizeEmailInput();

        if ($this->filled('username')) {
            $this->merge(['username' => strtolower(ltrim((string) $this->input('username'), '@'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = app(CurrentUser::class)->user();

        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:255'],
            // Compared case-insensitively, so a handle stored before lowercasing ("Mali") still blocks "mali".
            'username' => ['sometimes', 'required', 'string', 'regex:/^[a-z0-9_.]{3,20}$/i', function (string $attribute, mixed $value, Closure $fail) use ($user): void {
                if (is_string($value) && User::query()->whereRaw('LOWER(username) = ?', [strtolower($value)])->whereKeyNot($user->id)->exists()) {
                    $fail('The username has already been taken.');
                }
            }],
            // Accepted only unchanged (forms send the whole profile); a new address goes through PUT /api/me/email.
            'email' => ['sometimes', 'required', 'string', function (string $attribute, mixed $value, Closure $fail) use ($user): void {
                if (! is_string($value) || $value !== EmailAddress::normalize($user->email)) {
                    $fail('Change your email from the sign-in email form: it needs your password and a confirmation link.');
                }
            }],
            'location' => ['sometimes', 'nullable', 'string', 'max:120'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:160'],
            'profile_visibility' => ['sometimes', 'required', Rule::in(['public', 'followers', 'private'])],
        ];
    }

    public function toDto(): UpdateProfileData
    {
        return UpdateProfileData::fromValidated($this->safe()->except('email'));
    }
}
