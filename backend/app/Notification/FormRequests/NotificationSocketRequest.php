<?php

namespace App\Notification\FormRequests;

use App\Auth\CurrentUser\CurrentUser;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Pusher-protocol channel authorization for the signed-in user's own notifications channel
 * (`notifications.{id}`). Whether the id is theirs is decided by the channel callback in routes/channels.php.
 */
class NotificationSocketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CurrentUser::class)->isAuthenticated();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'socket_id' => ['required', 'string', 'max:100', 'regex:/^\d+\.\d+$/'],
            'channel_name' => ['required', 'string', 'max:100', 'regex:/^private-notifications\.\d+$/'],
            'callback' => ['prohibited'],
        ];
    }
}
