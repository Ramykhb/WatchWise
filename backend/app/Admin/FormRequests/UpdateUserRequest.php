<?php

namespace App\Admin\FormRequests;

use App\Admin\DTOs\Requests\AdminUserQuery;
use App\Admin\DTOs\Requests\UpdateUserData;
use App\Admin\Support\SuspensionRules;
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
     * `reason` and `suspended_until` describe a suspension, so they are refused unless `is_active` is false.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $notSuspending = ! $this->has('is_active') || $this->boolean('is_active');

        return [
            'role' => ['required_without:is_active', Rule::in(AdminUserQuery::ROLES)],
            'is_active' => ['required_without:role', 'boolean'],
            'reason' => [Rule::prohibitedIf($notSuspending), ...SuspensionRules::reason()],
            'suspended_until' => [Rule::prohibitedIf($notSuspending), ...SuspensionRules::until()],
        ];
    }

    public function toDto(): UpdateUserData
    {
        return UpdateUserData::fromValidated($this->validated());
    }
}
