<?php

namespace App\Room\FormRequests;

use App\Auth\CurrentUser\CurrentUser;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Pusher-protocol channel authorization for a room's private channel. Whether the user may listen to
 * that room is decided by the channel callback in routes/channels.php (the room policy's `view`).
 */
class RoomSocketRequest extends FormRequest
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
            'channel_name' => ['required', 'string', 'max:100', 'regex:/^private-room\.\d+$/'],
            'callback' => ['prohibited'],
        ];
    }
}
