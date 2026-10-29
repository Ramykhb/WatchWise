<?php

namespace App\Admin\FormRequests;

use App\Admin\DTOs\Requests\AdminStatsQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AdminStatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('admin.dashboard');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'days' => ['sometimes', 'integer', 'min:1', 'max:'.AdminStatsQuery::MAX_DAYS],
            'range' => ['sometimes', Rule::in(AdminStatsQuery::RANGES)],
        ];
    }

    public function toDto(): AdminStatsQuery
    {
        return AdminStatsQuery::fromValidated($this->validated());
    }
}
