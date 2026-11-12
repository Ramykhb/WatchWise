<?php

namespace App\Admin\FormRequests;

use App\MovieList\DTOs\Requests\StaffListQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AdminListIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('admin.lists');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'visibility' => ['sometimes', 'nullable', Rule::in(StaffListQuery::VISIBILITIES)],
            'owner_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'reported' => ['sometimes', 'nullable', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.StaffListQuery::MAX_PER_PAGE],
        ];
    }

    public function toDto(): StaffListQuery
    {
        return StaffListQuery::fromValidated($this->validated());
    }
}
