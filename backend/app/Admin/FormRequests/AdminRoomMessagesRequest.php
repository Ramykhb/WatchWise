<?php

namespace App\Admin\FormRequests;

use App\Admin\DTOs\Requests\AdminRoomMessagesQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AdminRoomMessagesRequest extends FormRequest
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
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.AdminRoomMessagesQuery::MAX_PER_PAGE],
        ];
    }

    public function toDto(): AdminRoomMessagesQuery
    {
        return AdminRoomMessagesQuery::fromValidated($this->validated());
    }
}
