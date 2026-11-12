<?php

namespace App\Admin\FormRequests;

use App\Rating\DTOs\Requests\RatingBurstQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AdminRatingBurstRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('admin.ratings');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'hours' => ['sometimes', 'integer', 'between:'.RatingBurstQuery::MIN_HOURS.','.RatingBurstQuery::MAX_HOURS],
            'min' => ['sometimes', 'integer', 'between:'.RatingBurstQuery::MIN_RATINGS.','.RatingBurstQuery::MAX_RATINGS],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.RatingBurstQuery::MAX_PER_PAGE],
        ];
    }

    public function toDto(): RatingBurstQuery
    {
        return RatingBurstQuery::fromValidated($this->validated());
    }
}
