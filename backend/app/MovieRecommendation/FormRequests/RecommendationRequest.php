<?php

namespace App\MovieRecommendation\FormRequests;

use App\MovieRecommendation\DTOs\Requests\RecommendationQuery;
use Illuminate\Foundation\Http\FormRequest;

class RecommendationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'latitude' => ['required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['required_with:latitude', 'numeric', 'between:-180,180'],
        ];
    }

    public function toDto(): RecommendationQuery
    {
        return RecommendationQuery::fromValidated($this->validated());
    }
}
