<?php

namespace App\Admin\FormRequests;

use App\Admin\DTOs\Requests\AdminAuditQuery;
use App\Admin\Support\AdminAction;
use App\Admin\Support\AdminSubjectType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AdminAuditIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('admin.audit');
    }

    /**
     * A subject id only means something next to its type (user 5 and review 5 are different records).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['sometimes', 'nullable', Rule::enum(AdminAction::class)],
            'actor_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'subject_type' => ['nullable', 'required_with:subject_id', Rule::enum(AdminSubjectType::class)],
            'subject_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.AdminAuditQuery::MAX_PER_PAGE],
        ];
    }

    public function toDto(): AdminAuditQuery
    {
        return AdminAuditQuery::fromValidated($this->validated());
    }
}
