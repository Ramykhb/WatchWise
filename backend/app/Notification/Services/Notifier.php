<?php

namespace App\Notification\Services;

use App\Models\DiscussionRoom;
use App\Models\Message;
use App\Models\MovieNight;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Writes the notifications one user's action creates for another. `data` stores the sentence
 * parts the Notifications page shows ("{actor} invited you to {target}") plus where it links.
 */
class Notifier
{
    public function followed(User $actor, User $followed): void
    {
        $this->send($followed, $actor, 'follow', [
            'text' => 'started following you',
            'to' => "/profile/{$actor->username}",
            'action' => 'View profile',
        ]);
    }

    public function invited(User $organizer, User $guest, MovieNight $night): void
    {
        $this->send($guest, $organizer, 'invite', [
            'text' => 'invited you to',
            'target' => $night->title,
            'detail' => $night->scheduled_at->format('D, M j · g:i A').' · '.($night->meeting_link ? 'Online' : ($night->location ?: 'TBD')),
            'to' => "/movie-nights/{$night->id}",
            'action' => 'Respond',
        ]);
    }

    public function answered(User $guest, MovieNight $night, string $status): void
    {
        $verb = ['accepted' => 'is going to', 'maybe' => 'might come to', 'declined' => "can't make it to"][$status] ?? 'answered';

        $this->send($night->organizer, $guest, 'invite', [
            'text' => $verb,
            'target' => $night->title,
            'to' => "/movie-nights/{$night->id}",
            'action' => 'See guests',
        ]);
    }

    public function voted(User $voter, MovieNight $night): void
    {
        if ($voter->id === $night->organizer_id) {
            return;
        }

        $this->send($night->organizer, $voter, 'vote', [
            'text' => 'voted in',
            'target' => $night->title,
            'to' => "/movie-nights/{$night->id}",
            'action' => 'See votes',
        ]);
    }

    /** Everyone @mentioned in a room message, except the author. */
    public function mentioned(User $author, DiscussionRoom $room, Message $message): void
    {
        preg_match_all('/@([a-z0-9_.]{3,30})/i', $message->content, $matches);
        $usernames = array_unique(array_map('strtolower', $matches[1]));
        if ($usernames === []) {
            return;
        }

        User::whereIn('username', $usernames)->whereKeyNot($author->id)->get()
            ->each(fn (User $user) => $this->send($user, $author, 'mention', [
                'text' => 'mentioned you in',
                'target' => $room->name,
                'detail' => '“'.Str::limit($message->content, 120).'”',
                'to' => "/rooms/{$room->id}",
                'action' => 'Reply',
            ]));
    }

    /**
     * @param  array<string, string>  $data
     */
    private function send(User $recipient, ?User $actor, string $kind, array $data): void
    {
        Notification::create([
            'user_id' => $recipient->id,
            'type' => $kind,
            'data' => [...$data, 'actor_id' => $actor?->id],
        ]);
    }
}
