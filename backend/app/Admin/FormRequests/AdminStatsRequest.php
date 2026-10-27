<?php

namespace App\Admin\FormRequests;

use App\Admin\DTOs\Requests\AdminStatsQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

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
        ];
    }

    public function toDto(): AdminStatsQuery
    {
        return AdminStatsQuery::fromValidated($this->validated());
    }
}
