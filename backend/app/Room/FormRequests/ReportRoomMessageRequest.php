<?php

namespace App\Room\FormRequests;

use App\Report\DTOs\Requests\ReportContentData;
use App\Report\Support\ReportTargetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** POST /api/rooms/{room}/messages/{message}/report — a member reports another member's message in a room they can see. */
class ReportRoomMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('report', $this->route('message'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::in(ReportTargetType::Message->reasons())],
            'details' => ['sometimes', 'nullable', 'string', 'max:'.ReportContentData::MAX_DETAILS],
        ];
    }

    public function toDto(): ReportContentData
    {
        return ReportContentData::fromValidated($this->validated());
    }
}
