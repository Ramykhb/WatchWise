<?php

namespace App\Admin\FormRequests;

use App\Admin\DTOs\Requests\BulkSuspendData;
use App\Admin\Support\SuspensionRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class BulkSuspendUsersRequest extends FormRequest
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
            'user_ids' => ['required', 'array', 'min:1', 'max:'.BulkSuspendData::MAX_USERS],
            'user_ids.*' => ['integer', 'min:1', 'distinct'],
            'reason' => SuspensionRules::reason(),
            'suspended_until' => SuspensionRules::until(),
        ];
    }

    public function toDto(): BulkSuspendData
    {
        return BulkSuspendData::fromValidated($this->validated());
    }
}
