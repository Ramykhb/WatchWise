<?php

namespace App\User\FormRequests;

use App\Auth\CurrentUser\CurrentUser;
use App\User\DTOs\Requests\UpdateProfileData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** "@Jane" and "jane" are the same handle. */
    protected function prepareForValidation(): void
    {
        if ($this->filled('username')) {
            $this->merge(['username' => strtolower(ltrim((string) $this->input('username'), '@'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = app(CurrentUser::class)->id();

        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:255'],
            'username' => ['sometimes', 'required', 'string', 'regex:/^[a-z0-9_.]{3,20}$/i', Rule::unique('users', 'username')->ignore($userId)],
            'email' => ['sometimes', 'required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'location' => ['sometimes', 'nullable', 'string', 'max:120'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:160'],
            'profile_visibility' => ['sometimes', 'required', Rule::in(['public', 'followers', 'private'])],
        ];
    }

    public function toDto(): UpdateProfileData
    {
        return UpdateProfileData::fromValidated($this->validated());
    }
}
