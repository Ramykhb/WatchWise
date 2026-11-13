<?php

namespace App\MovieList\FormRequests;

use App\Report\DTOs\Requests\ReportContentData;
use App\Report\Support\ReportTargetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** POST /api/lists/{list}/report — a member reports another member's list they can see. */
class ReportMovieListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('report', $this->route('list'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::in(ReportTargetType::MovieList->reasons())],
            'details' => ['sometimes', 'nullable', 'string', 'max:'.ReportContentData::MAX_DETAILS],
        ];
    }

    public function toDto(): ReportContentData
    {
        return ReportContentData::fromValidated($this->validated());
    }
}
