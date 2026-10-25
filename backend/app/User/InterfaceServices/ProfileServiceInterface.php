<?php

namespace App\User\InterfaceServices;

use App\Auth\CurrentUser\CurrentUser;
use App\Models\User;
use App\User\DTOs\Requests\UserSearchQuery;
use App\User\DTOs\Responses\FollowResponse;
use App\User\DTOs\Responses\ProfileResponse;
use App\User\DTOs\Responses\UserSearchResponse;
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

    /** Follows the user; a no-op when already following. */
    public function follow(User $follower, User $target): FollowResponse;

    public function unfollow(User $follower, User $target): FollowResponse;
}
