<?php

namespace App\User\FormRequests;

use App\User\DTOs\Requests\DeleteAccountData;
use Illuminate\Foundation\Http\FormRequest;

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
            'password' => ['required', 'string'],
        ];
    }

    public function toDto(): DeleteAccountData
    {
        return DeleteAccountData::fromValidated($this->validated());
    }
}
