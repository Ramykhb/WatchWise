<?php

namespace App\Admin\FormRequests;

use App\Admin\DTOs\Requests\ModerateReviewData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ModerateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('admin.reviews');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(ModerateReviewData::DECISIONS)],
        ];
    }

    public function toDto(): ModerateReviewData
    {
        return ModerateReviewData::fromValidated($this->validated());
    }
}
