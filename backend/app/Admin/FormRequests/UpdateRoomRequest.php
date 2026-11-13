<?php

namespace App\Admin\FormRequests;

use App\Admin\DTOs\Requests\UpdateRoomData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('admin.rooms');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_locked' => ['required', 'boolean'],
        ];
    }

    public function toDto(): UpdateRoomData
    {
        return UpdateRoomData::fromValidated($this->validated());
    }
}
