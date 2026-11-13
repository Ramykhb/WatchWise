<?php

namespace App\Admin\FormRequests;

use App\Admin\DTOs\Requests\AdminGenreQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AdminGenreIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('admin.genres.view');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::in(AdminGenreQuery::TYPES)],
        ];
    }

    public function toDto(): AdminGenreQuery
    {
        return AdminGenreQuery::fromValidated($this->validated());
    }
}
