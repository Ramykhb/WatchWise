<?php

namespace App\MovieNight\FormRequests;

use Illuminate\Foundation\Http\FormRequest;

class InvitationTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['token' => ['required', 'string', 'size:64', 'regex:/^[A-Za-z0-9]+$/']];
    }
}
