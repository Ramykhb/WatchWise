<?php

namespace App\Admin\FormRequests;

use App\Admin\DTOs\Requests\UserNoteQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AdminUserNoteIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('admin.users');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.UserNoteQuery::MAX_PER_PAGE],
        ];
    }

    public function toDto(): UserNoteQuery
    {
        return UserNoteQuery::fromValidated($this->validated());
    }
}
