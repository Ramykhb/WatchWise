<?php

namespace App\Room\FormRequests;

use App\Auth\CurrentUser\CurrentUser;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Pusher-protocol channel authorization for the Rooms page's private channels: a room's own channel
 * (`room.{id}`) and the room-list channels (`rooms.public`, `rooms.user.{id}`). Who may listen is decided by
 * the channel callbacks in routes/channels.php.
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
            'channel_name' => ['required', 'string', 'max:100', 'regex:/^private-(room\.\d+|rooms\.public|rooms\.user\.\d+)$/'],
            'callback' => ['prohibited'],
        ];
    }
}
