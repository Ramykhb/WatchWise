<?php

namespace App\Admin\FormRequests;

use App\Admin\DTOs\Requests\ModerateReviewData;
use App\Admin\DTOs\Requests\UpdateReviewData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('admin.reviews');
    }

    /**
     * A decision (`status`), a spoiler flag (`has_spoilers`) or both; one of them is required.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required_without:has_spoilers', 'nullable', Rule::in(ModerateReviewData::DECISIONS)],
            'has_spoilers' => ['required_without:status', 'nullable', 'boolean'],
        ];
    }

    public function toDto(): UpdateReviewData
    {
        return UpdateReviewData::fromValidated($this->validated());
    }
}
