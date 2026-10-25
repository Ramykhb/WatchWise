<?php

namespace App\Groq\FormRequests;

use App\Auth\CurrentUser\CurrentUser;
use Illuminate\Foundation\Http\FormRequest;

class ChatSocketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CurrentUser::class)->isAuthenticated();
    }

    public function rules(): array
    {
        return [
            'socket_id' => ['required', 'string', 'max:100', 'regex:/^\d+\.\d+$/'],
            'channel_name' => ['required', 'string', 'max:150', 'regex:/^private-chat\.\d+\.[a-f0-9-]{36}$/i'],
            'callback' => ['prohibited'],
        ];
    }
}
