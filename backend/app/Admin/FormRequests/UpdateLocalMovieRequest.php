<?php

namespace App\Admin\FormRequests;

use App\Movie\DTOs\Requests\UpdateLocalMovieData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateLocalMovieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('admin.movies');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'overview' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_hidden' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
        ];
    }

    public function toDto(): UpdateLocalMovieData
    {
        return UpdateLocalMovieData::fromValidated($this->validated());
    }
}
