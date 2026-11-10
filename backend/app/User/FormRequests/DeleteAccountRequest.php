<?php

namespace App\User\FormRequests;

use App\User\DTOs\Requests\DeleteAccountData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Any signed-in member may ask. Whether `password` is required depends on the account (one whose password
 * proves nothing confirms from its inbox instead), so AccountDeletionService enforces it.
 */
class DeleteAccountRequest extends FormRequest
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
            'password' => ['nullable', 'string'],
        ];
    }

    public function toDto(): DeleteAccountData
    {
        return DeleteAccountData::fromValidated($this->validated());
    }
}
