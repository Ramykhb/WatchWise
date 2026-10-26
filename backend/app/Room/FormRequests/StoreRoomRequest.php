<?php

namespace App\Room\FormRequests;

use App\Models\DiscussionRoom;
use App\Room\DTOs\Requests\CreateRoomData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', DiscussionRoom::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:80'],
            'tmdb_id' => ['required', 'integer', 'min:1'],
            'spoilers' => ['sometimes', 'boolean'],
        ];
    }

    public function toDto(): CreateRoomData
    {
        return CreateRoomData::fromValidated($this->validated());
    }
}
