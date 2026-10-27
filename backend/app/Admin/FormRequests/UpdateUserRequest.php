<?php

namespace App\Admin\FormRequests;

use App\Admin\DTOs\Requests\AdminUserQuery;
use App\Admin\DTOs\Requests\UpdateUserData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
            'role' => ['required_without:is_active', Rule::in(AdminUserQuery::ROLES)],
            'is_active' => ['required_without:role', 'boolean'],
        ];
    }

    public function toDto(): UpdateUserData
    {
        return UpdateUserData::fromValidated($this->validated());
    }
}
