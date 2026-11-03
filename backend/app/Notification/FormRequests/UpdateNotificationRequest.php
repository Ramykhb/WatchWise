<?php

namespace App\Notification\FormRequests;

use App\Notification\DTOs\Requests\UpdateNotificationData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('notification'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'read' => ['required', 'boolean'],
        ];
    }

    public function toDto(): UpdateNotificationData
    {
        return UpdateNotificationData::fromValidated($this->validated());
    }
}
