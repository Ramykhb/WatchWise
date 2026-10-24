<?php

namespace App\Weather\FormRequests;

use App\Weather\DTOs\Requests\WeatherMoodQuery;
use Illuminate\Foundation\Http\FormRequest;

class WeatherMoodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'condition' => ['required', 'string', 'max:50'],
            'temperature' => ['required', 'numeric'],
            'humidity' => ['required', 'numeric'],
            'wind' => ['required', 'numeric'],
            'precipitation' => ['required', 'numeric'],
        ];
    }

    public function toDto(): WeatherMoodQuery
    {
        return WeatherMoodQuery::fromValidated($this->validated());
    }
}
