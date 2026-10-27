<?php

namespace App\Admin\FormRequests;

use App\Admin\DTOs\Requests\AdminUserQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AdminUserIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('admin.users');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'role' => ['sometimes', 'nullable', Rule::in(AdminUserQuery::ROLES)],
            'status' => ['sometimes', 'nullable', Rule::in(AdminUserQuery::STATUSES)],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.AdminUserQuery::MAX_PER_PAGE],
        ];
    }

    public function toDto(): AdminUserQuery
    {
        return AdminUserQuery::fromValidated($this->validated());
    }
}
