<?php

namespace App\Admin\FormRequests;

use App\Admin\DTOs\Requests\AddUserNoteData;
use App\Models\AdminUserNote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AddUserNoteRequest extends FormRequest
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
            'body' => ['required', 'string', 'max:'.AdminUserNote::BODY_MAX],
        ];
    }

    public function toDto(): AddUserNoteData
    {
        return AddUserNoteData::fromValidated($this->validated());
    }
}
