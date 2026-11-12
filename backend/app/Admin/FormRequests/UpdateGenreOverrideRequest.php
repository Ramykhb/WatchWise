<?php

namespace App\Admin\FormRequests;

use App\Admin\DTOs\Requests\UpdateGenreOverrideData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateGenreOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('admin.genres');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:60'],
            'is_hidden' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'nullable', 'integer:strict', 'min:0', 'max:999'],
        ];
    }

    public function toDto(): UpdateGenreOverrideData
    {
        return UpdateGenreOverrideData::fromValidated($this->validated());
    }
}
