<?php

namespace App\Admin\FormRequests;

use App\Support\MediaType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AdminRatingFilmRequest extends FormRequest
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
            'media_type' => ['sometimes', 'nullable', Rule::in(MediaType::ALL)],
        ];
    }

    public function mediaType(): string
    {
        return $this->validated('media_type') ?? MediaType::MOVIE;
    }
}
