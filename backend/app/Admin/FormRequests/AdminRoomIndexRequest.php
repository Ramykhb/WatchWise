<?php

namespace App\Admin\FormRequests;

use App\Admin\DTOs\Requests\AdminRoomQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AdminRoomIndexRequest extends FormRequest
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
            'q' => ['sometimes', 'nullable', 'string', 'max:200'],
            'visibility' => ['sometimes', 'nullable', Rule::in(AdminRoomQuery::VISIBILITIES)],
            'creator_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'tmdb_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'locked' => ['sometimes', 'nullable', 'boolean'],
            'reported' => ['sometimes', 'nullable', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.AdminRoomQuery::MAX_PER_PAGE],
        ];
    }

    public function toDto(): AdminRoomQuery
    {
        return AdminRoomQuery::fromValidated($this->validated());
    }
}
