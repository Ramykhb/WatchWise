<?php

namespace App\Room\FormRequests;

use App\Room\DTOs\Requests\ReadRoomMessagesData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReadRoomMessagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('view', $this->route('room'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message_ids' => ['required', 'array', 'min:1', 'max:500'],
            'message_ids.*' => ['integer', 'min:1', 'distinct'],
        ];
    }

    public function toDto(): ReadRoomMessagesData
    {
        return ReadRoomMessagesData::fromValidated($this->validated());
    }
}
