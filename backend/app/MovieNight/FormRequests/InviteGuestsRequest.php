<?php

namespace App\MovieNight\FormRequests;

use App\MovieNight\DTOs\Requests\InviteGuestsData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InviteGuestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('night'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'invitees' => ['required', 'array', 'min:1', 'max:30'],
            'invitees.*' => ['string', 'distinct', Rule::exists('users', 'username')],
        ];
    }

    public function toDto(): InviteGuestsData
    {
        return InviteGuestsData::fromValidated($this->validated());
    }
}
