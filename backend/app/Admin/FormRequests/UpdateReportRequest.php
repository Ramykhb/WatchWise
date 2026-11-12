<?php

namespace App\Admin\FormRequests;

use App\Admin\DTOs\Requests\UpdateReportData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('admin.reports');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(UpdateReportData::DECISIONS)],
            'note' => ['sometimes', 'nullable', 'string', 'max:'.UpdateReportData::MAX_NOTE],
            'apply_to_target' => ['sometimes', 'boolean'],
        ];
    }

    public function toDto(): UpdateReportData
    {
        return UpdateReportData::fromValidated($this->validated());
    }
}
