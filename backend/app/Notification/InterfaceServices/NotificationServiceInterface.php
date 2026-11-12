<?php

namespace App\Notification\InterfaceServices;

use App\Models\DiscussionRoom;
use App\Models\Message;
use App\Models\MovieList;
use App\Models\MovieNight;
use App\Models\Review;
use App\Models\User;
use App\Notification\Support\ListRestriction;

/**
 * What other modules call when something happens that someone should hear about. Each notification is stored
 * for the people who haven't switched its type off and pushed to their open tabs over Reverb. Nobody is ever
 * notified about their own action. Call these after the change is committed.
 *
 * Pending follow requests are also kept here: a `follow_request` notification is the request itself.
 */
interface NotificationServiceInterface
{
    public function followed(User $follower, User $target): void;

    /** Asks `$target` to accept `$requester` as a follower; asking again changes nothing. */
    public function requestFollow(User $requester, User $target): void;

    public function hasRequestedFollow(User $requester, User $target): bool;

    /**
     * @param  list<int>  $userIds
     * @return list<int> those `$requester` has asked to follow and who haven't answered yet
     */
    public function followRequestedAmong(User $requester, array $userIds): array;

    /** The request was cancelled by `$requester` or declined by `$target`. Returns whether one was pending. */
    public function removeFollowRequest(User $requester, User $target): bool;

    /** `$target` accepted: their request becomes "started following you" and `$requester` hears about it. */
    public function acceptFollowRequest(User $requester, User $target): void;

    /**
     * @param  list<int>  $inviteeIds
     */
    public function roomInvited(DiscussionRoom $room, User $inviter, array $inviteeIds): void;

    /** `$invitee` joined the private room `$inviterId` invited them to. */
    public function roomInviteAccepted(DiscussionRoom $room, User $invitee, int $inviterId): void;

    /** The invitation is withdrawn from the decliner's notifications, since it opens a room they can't see. */
    public function roomInviteDeclined(DiscussionRoom $room, User $invitee): void;

    /**
     * Members get one "new messages" notification per room that counts up until they read the room; people
     * named with @username also get a mention.
     *
     * @param  list<int>  $memberIds  members waiting to read the message (not the sender)
     * @param  list<int>  $mentionedIds  people mentioned who can see the room
     */
    public function roomMessageSent(DiscussionRoom $room, Message $message, array $memberIds, array $mentionedIds): void;

    /** The reader opened the room: its "new messages" and mentions are read. */
    public function roomRead(DiscussionRoom $room, User $reader): void;

    /**
     * The room was deleted: notifications that open it (including the staff notices about it) are removed, and its
     * members and invitees hear who deleted it.
     *
     * @param  list<int>  $audienceIds  the room's members and invitees
     */
    public function roomDeleted(DiscussionRoom $room, User $deleter, array $audienceIds): void;

    /**
     * Staff deleted the room. Its notifications are removed like roomDeleted() does, but its members and invitees
     * hear it from the WatchWise team, not from the staff member (`room_deleted` without an actor, which still
     * respects their switch), and the room's creator, unless they are the staff member, always gets `room_removed`,
     * which has no switch. The creator is not sent `room_deleted` as well.
     *
     * @param  list<int>  $audienceIds  the room's members and invitees
     */
    public function roomRemovedByStaff(DiscussionRoom $room, User $staff, array $audienceIds): void;

    /**
     * A message was deleted by staff, so nothing may still quote it: the mentions of it are withdrawn, and the
     * room's "new messages" notifications stop quoting it and count one fewer for each member who had yet to read it.
     * Rows from before they stored a message id are matched by the quoted text.
     *
     * @param  list<int>  $unreadByIds  the members who had not read the message
     */
    public function roomMessageDeleted(DiscussionRoom $room, Message $message, array $unreadByIds): void;

    /**
     * @param  list<int>  $guestIds
     */
    public function nightInvited(MovieNight $night, User $host, array $guestIds): void;

    /** A guest answered (accepted, maybe or declined); the host hears about it. */
    public function nightAnswered(MovieNight $night, User $guest, string $status): void;

    public function nightVoted(MovieNight $night, User $voter, int $tmdbId): void;

    /**
     * @param  string  $change  confirmed, cancelled, reopened or rescheduled
     * @param  list<int>  $guestIds  the guests who haven't declined
     */
    public function nightChanged(MovieNight $night, string $change, array $guestIds): void;

    public function reviewMarkedHelpful(Review $review, User $voter): void;

    /** Taking the vote back takes its notification back too. */
    public function reviewUnmarkedHelpful(Review $review, User $voter): void;

    /**
     * Staff removed the review (rejected or deleted it) and its author is told which film it was about. The
     * notice carries neither the review's text nor who removed it, and ignores the author's notification settings.
     */
    public function reviewRemoved(Review $review): void;

    /**
     * Staff restricted the list (made it private, removed a film from it or deleted it) and its owner is told which
     * list it was. `$film` names the removed title for `ItemRemoved`. The notice carries nobody who acted and
     * ignores the owner's notification settings. A deleted list may be passed: only its id, title and owner are read.
     *
     * @param  array{tmdb_id: int, media_type: string}|null  $film
     */
    public function listRestricted(MovieList $list, ListRestriction $restriction, ?array $film = null): void;

    /**
     * Staff removed the message and its author is told which room and film it was in. The notice carries neither
     * the message's text nor who removed it, and ignores the author's notification settings.
     */
    public function messageRemoved(DiscussionRoom $room, Message $message): void;

    /**
     * Staff locked or unlocked the room and its creator is told (nobody when the creator's account is gone). The
     * notice carries no staff identity and ignores the creator's notification settings.
     */
    public function roomLockChanged(DiscussionRoom $room, bool $locked): void;

    /**
     * Staff removed the member's profile picture or bio. `$action` is `avatar_removed` or `bio_removed`. The notice
     * names neither the removed content nor who removed it, and ignores the member's notification settings.
     */
    public function profileChanged(User $member, string $action): void;
}
