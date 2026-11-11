<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = ['user_id', 'type', 'data', 'read_at', 'pending_follow_requester_id'];

    public const UPDATED_AT = null;

    /**
     * A room message the user still has to read (`data`: room_id, message_id). `read_at` is set once they have
     * seen it, and the message is deleted, with these rows, once its 24 hours are up and every recipient has.
     * These are read receipts for the Room module, never shown in the notifications feed.
     */
    public const ROOM_MESSAGE = 'room_message';

    /** Someone followed you (`data`: actor_id). An accepted follow request becomes one of these. */
    public const FOLLOW = 'follow';

    /**
     * Someone asked to follow your followers-only or private profile (`data`: actor_id). The row is the pending
     * request itself: accepting turns it into a FOLLOW, declining or the requester cancelling deletes it.
     */
    public const FOLLOW_REQUEST = 'follow_request';

    /** The person you asked to follow accepted (`data`: actor_id). */
    public const FOLLOW_ACCEPTED = 'follow_accepted';

    /** A member invited you to a private room (`data`: actor_id, room_id, room_name). */
    public const ROOM_INVITE = 'room_invite';

    /** Someone you invited to a private room joined it (`data`: actor_id, room_id, room_name). */
    public const ROOM_JOINED = 'room_joined';

    /** A room you were a member of, or invited to, was deleted by its creator or an admin (`data`: actor_id, room_name). */
    public const ROOM_DELETED = 'room_deleted';

    /**
     * New messages in a room you are a member of: one row per room, updated as messages arrive and marked read
     * when you read the room (`data`: room_id, room_name, actor_id of the latest sender, sender_ids, count, preview).
     */
    public const NEW_MESSAGES = 'new_messages';

    /** Someone wrote @yourusername in a room (`data`: actor_id, room_id, room_name, preview). */
    public const MENTION = 'mention';

    /** The host invited you to a movie night (`data`: actor_id, night_id, title). */
    public const NIGHT_INVITE = 'night_invite';

    /** A guest answered your movie night invite (`data`: actor_id, night_id, title, status). */
    public const NIGHT_RSVP = 'night_rsvp';

    /** A guest voted on your movie night's shortlist (`data`: actor_id, night_id, title, tmdb_id). */
    public const NIGHT_VOTE = 'night_vote';

    /**
     * The host confirmed, cancelled, reopened or moved a movie night you are invited to
     * (`data`: actor_id, night_id, title, change, tmdb_id of the pick when confirmed).
     */
    public const NIGHT_UPDATE = 'night_update';

    /** Someone marked your review helpful (`data`: actor_id, review_id, tmdb_id, media_type). */
    public const REVIEW_HELPFUL = 'review_helpful';

    /**
     * Staff removed your review, by rejecting or deleting it (`data`: tmdb_id, media_type, action `removed`). It
     * carries no actor and never the review's text. It is a notice about your own content, so it has no switch
     * and always arrives.
     */
    public const REVIEW_REMOVED = 'review_removed';

    /**
     * The types a member can switch off (the keys of `settings.notifications`, all on by default). Follow
     * requests and removed-review notices are not among them: they always arrive.
     *
     * @var list<string>
     */
    public const OPTIONAL_TYPES = [
        self::FOLLOW, self::FOLLOW_ACCEPTED,
        self::ROOM_INVITE, self::ROOM_JOINED, self::ROOM_DELETED, self::NEW_MESSAGES, self::MENTION,
        self::NIGHT_INVITE, self::NIGHT_RSVP, self::NIGHT_VOTE, self::NIGHT_UPDATE,
        self::REVIEW_HELPFUL,
    ];

    /**
     * Everything the notifications feed and the header bell show.
     *
     * @var list<string>
     */
    public const FEED_TYPES = [self::FOLLOW_REQUEST, self::REVIEW_REMOVED, ...self::OPTIONAL_TYPES];

    protected function casts(): array
    {
        return ['data' => 'array', 'read_at' => 'datetime', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
