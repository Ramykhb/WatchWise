<?php

namespace App\Room\FormRequests;

use App\Room\DTOs\Requests\InviteToRoomData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InviteToRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('invite', $this->route('room'));
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

    public function toDto(): InviteToRoomData
    {
        return InviteToRoomData::fromValidated($this->validated());
    }
}
