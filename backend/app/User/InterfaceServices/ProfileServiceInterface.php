<?php

namespace App\User\InterfaceServices;

use App\Auth\CurrentUser\CurrentUser;
use App\Models\User;
use App\Report\DTOs\Requests\ReportContentData;
use App\Report\DTOs\Responses\ReportContentResponse;
use App\User\DTOs\Requests\UserSearchQuery;
use App\User\DTOs\Responses\FollowResponse;
use App\User\DTOs\Responses\ProfileResponse;
use App\User\DTOs\Responses\UserSearchResponse;
use App\User\Exceptions\FollowRequestNotFound;
use App\User\Exceptions\ProfileSectionHidden;
use Illuminate\Database\Eloquent\Collection;

interface ProfileServiceInterface
{
    /** Find members by name or @username; without text it suggests the most-followed members. */
    public function search(UserSearchQuery $query, CurrentUser $viewer): UserSearchResponse;

    /**
     * Everything the Profile page shows, filtered by the owner's privacy settings. A profile the
     * viewer may not open comes back with only the name and handle (enough to follow them).
     */
    public function profile(User $user, CurrentUser $viewer): ProfileResponse;

    /**
     * @return Collection<int, User>
     *
     * @throws ProfileSectionHidden
     */
    public function followers(User $user, CurrentUser $viewer): Collection;

    /**
     * @return Collection<int, User>
     *
     * @throws ProfileSectionHidden
     */
    public function following(User $user, CurrentUser $viewer): Collection;

    /**
     * Follows a public profile at once; a followers-only or private one gets a follow request to accept.
     * A no-op when already following or already asked.
     */
    public function follow(User $follower, User $target): FollowResponse;

    /** Reports another member's profile (the caller has checked the reporter may report it); reporting it twice files one report, reopened if staff had closed it. */
    public function report(User $profile, User $reporter, ReportContentData $data): ReportContentResponse;

    /** Unfollows, or withdraws a pending follow request. */
    public function unfollow(User $follower, User $target): FollowResponse;

    /**
     * `$me` accepts `$requester` as a follower.
     *
     * @throws FollowRequestNotFound
     */
    public function acceptFollowRequest(User $me, User $requester): void;

    /** `$me` declines `$requester`'s follow request; nothing happens when there is none. */
    public function declineFollowRequest(User $me, User $requester): void;

    /**
     * How many members follow the member and how many they follow, whatever their privacy settings say.
     *
     * @return array{followers: int, following: int}
     */
    public function followCounts(User $user): array;
}
