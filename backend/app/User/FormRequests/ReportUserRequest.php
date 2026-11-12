<?php

namespace App\User\FormRequests;

use App\Report\DTOs\Requests\ReportContentData;
use App\Report\Support\ReportTargetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** POST /api/users/{username}/report — a member reports another active member's profile (their own is refused). */
class ReportUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('report', $this->route('user'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::in(ReportTargetType::Profile->reasons())],
            'details' => ['sometimes', 'nullable', 'string', 'max:'.ReportContentData::MAX_DETAILS],
        ];
    }

    public function toDto(): ReportContentData
    {
        return ReportContentData::fromValidated($this->validated());
    }
}
