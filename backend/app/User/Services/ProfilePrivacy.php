<?php

namespace App\User\Services;

use App\Auth\CurrentUser\CurrentUser;
use App\Models\User;
use App\User\Policies\UserPolicy;

/**
 * What a viewer may see of someone's profile: the profile visibility (UserPolicy::view) first,
 * then the owner's current section switches from Settings › Privacy. The owner and admins see
 * everything. Used by the profile API and by every activity feed, so both follow the same rules.
 *
 * Sections: ratings, reviews, lists, watchlist, history (watched films), following (who they
 * follow and who follows them).
 */
class ProfilePrivacy
{
    public function __construct(private readonly UserPolicy $policy) {}

    public function canView(User $owner, CurrentUser $viewer): bool
    {
        return $this->policy->view($viewer, $owner);
    }

    public function isPrivileged(User $owner, CurrentUser $viewer): bool
    {
        return $viewer->id() === $owner->id || $viewer->isAdmin();
    }

    public function shows(User $owner, CurrentUser $viewer, string $section): bool
    {
        if ($this->isPrivileged($owner, $viewer)) {
            return true;
        }

        return $this->canView($owner, $viewer) && $owner->shares($section);
    }

    /**
     * `shows()` for one owner and viewer, deciding `canView()` at most once however many sections are asked
     * (or never, when the caller already knows it). A followers-only profile checks the follow with a query,
     * so a page reading many sections uses this.
     *
     * @return \Closure(string): bool
     */
    public function sectionsShownTo(User $owner, CurrentUser $viewer, ?bool $canView = null): \Closure
    {
        $privileged = $this->isPrivileged($owner, $viewer);

        return function (string $section) use ($owner, $viewer, $privileged, &$canView): bool {
            if ($privileged) {
                return true;
            }

            $canView ??= $this->canView($owner, $viewer);

            return $canView && $owner->shares($section);
        };
    }

    /** Reviews waiting for moderation are only shown to their author and admins. */
    public function seesUnapprovedReviews(User $owner, CurrentUser $viewer): bool
    {
        return $this->isPrivileged($owner, $viewer);
    }

    /** Followers-only and private profiles choose their followers: following them sends a request. */
    public function needsFollowApproval(User $owner): bool
    {
        return $owner->profile_visibility !== 'public';
    }
}
