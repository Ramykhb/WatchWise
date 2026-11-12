<?php

namespace App\Notification\InterfaceServices;

use App\Models\DiscussionRoom;
use App\Models\Message;
use App\Models\MovieNight;
use App\Models\Review;
use App\Models\User;

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
     * The room was deleted: notifications that open it are removed, and its members and invitees hear who
     * deleted it.
     *
     * @param  list<int>  $audienceIds  the room's members and invitees
     */
    public function roomDeleted(DiscussionRoom $room, User $deleter, array $audienceIds): void;

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
     * Staff removed the member's profile picture or bio. `$action` is `avatar_removed` or `bio_removed`. The notice
     * names neither the removed content nor who removed it, and ignores the member's notification settings.
     */
    public function profileChanged(User $member, string $action): void;
}
