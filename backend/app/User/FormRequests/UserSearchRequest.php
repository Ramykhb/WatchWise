<?php

namespace App\User\FormRequests;

use App\User\DTOs\Requests\UserSearchQuery;
use Illuminate\Foundation\Http\FormRequest;

class UserSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Nothing is rejected: `q` is free text and `limit` is clamped by the DTO.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function toDto(): UserSearchQuery
    {
        return UserSearchQuery::fromInput($this->query('q'), $this->query('limit'));
    }
}
