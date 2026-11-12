<?php

namespace App\Admin\FormRequests;

use App\Admin\DTOs\Requests\UpdateListVisibilityData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateListVisibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('admin.lists');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'visibility' => ['required', 'string', Rule::in(UpdateListVisibilityData::ALLOWED)],
        ];
    }

    public function toDto(): UpdateListVisibilityData
    {
        return UpdateListVisibilityData::fromValidated($this->validated());
    }
}
