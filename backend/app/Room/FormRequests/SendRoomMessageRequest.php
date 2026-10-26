<?php

namespace App\Room\FormRequests;

use App\Room\DTOs\Requests\SendRoomMessageData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SendRoomMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('post', $this->route('room'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:'.SendRoomMessageData::MAX_CHARS],
        ];
    }

    public function toDto(): SendRoomMessageData
    {
        return SendRoomMessageData::fromValidated($this->validated());
    }
}
