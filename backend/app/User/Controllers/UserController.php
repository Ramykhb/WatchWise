<?php

namespace App\User\Controllers;

use App\Auth\CurrentUser\CurrentUser;
use App\Auth\Support\AuthCookies;
use App\Models\User;
use App\User\FormRequests\DeleteAccountRequest;
use App\User\FormRequests\UpdatePasswordRequest;
use App\User\FormRequests\UpdatePreferencesRequest;
use App\User\FormRequests\UpdateProfileRequest;
use App\User\FormRequests\UploadAvatarRequest;
use App\User\FormRequests\UserSearchRequest;
use App\User\InterfaceServices\AccountServiceInterface;
use App\User\InterfaceServices\HomeServiceInterface;
use App\User\InterfaceServices\ProfileServiceInterface;
use App\User\Resources\MemberResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Everything about a user: the signed-in user's own account (/me), other members' profiles and
 * following (/users) and the Home page (/home).
 */
class UserController
{
    public function __construct(
        private readonly AccountServiceInterface $account,
        private readonly ProfileServiceInterface $profiles,
        private readonly HomeServiceInterface $home,
    ) {}

    /** GET /api/home — the signed-in Home page: busy rooms and your next movie night. */
    public function home(CurrentUser $current): JsonResponse
    {
        return response()->json($this->home->home($current));
    }

    /** GET /api/me — account details plus everything Settings and the header need. */
    public function showMe(CurrentUser $current): JsonResponse
    {
        return response()->json($this->account->show($current->user()));
    }

    /** PATCH /api/me — Settings › Profile and the visibility switch. */
    public function updateMe(UpdateProfileRequest $request, CurrentUser $current): JsonResponse
    {
        return response()->json($this->account->updateProfile($current->user(), $request->toDto()));
    }

    /** PATCH /api/me/preferences — favourite genres and the switches in Settings / Notifications. */
    public function updatePreferences(UpdatePreferencesRequest $request, CurrentUser $current): JsonResponse
    {
        return response()->json($this->account->updatePreferences($current->user(), $request->toDto()));
    }

    /** PUT /api/me/password — signs out every other device by revoking their refresh tokens. */
    public function updatePassword(UpdatePasswordRequest $request, CurrentUser $current): JsonResponse
    {
        $this->account->changePassword($current->user(), $request->toDto());

        return response()->json(['message' => 'Password updated. Other devices have been signed out.']);
    }

    /** POST /api/me/avatar — multipart `avatar`, stored on the public disk. */
    public function uploadAvatar(UploadAvatarRequest $request, CurrentUser $current): JsonResponse
    {
        return response()->json($this->account->uploadAvatar($current->user(), $request->avatar()));
    }

    /** DELETE /api/me/avatar */
    public function deleteAvatar(CurrentUser $current): JsonResponse
    {
        return response()->json($this->account->deleteAvatar($current->user()));
    }

    /** DELETE /api/me/social/{provider} */
    public function disconnectSocial(string $provider, CurrentUser $current): JsonResponse
    {
        return response()->json($this->account->disconnectSocial($current->user(), $provider));
    }

    /** DELETE /api/me — removes the account and everything it owns (FKs cascade). */
    public function destroyMe(DeleteAccountRequest $request, CurrentUser $current, AuthCookies $cookies): JsonResponse
    {
        $this->account->deleteAccount($current->user(), $request->toDto());

        return $cookies->clear(response()->json(['message' => 'Account deleted.']));
    }

    /**
     * GET /api/users?q=&limit= — find members by name or @username (header search, Discover,
     * invite pickers). Without `q` it suggests the most-followed members. Each result says
     * whether the signed-in user follows them, so Follow buttons start in the right state.
     */
    public function index(UserSearchRequest $request, CurrentUser $current): JsonResponse
    {
        return response()->json($this->profiles->search($request->toDto(), $current));
    }

    /** GET /api/users/{username} — everything the Profile page shows, filtered by the owner's privacy settings. */
    public function show(User $user, CurrentUser $current): JsonResponse
    {
        return response()->json($this->profiles->profile($user, $current));
    }

    /** GET /api/users/{username}/followers */
    public function followers(User $user, CurrentUser $current): JsonResponse
    {
        return response()->json(['users' => MemberResource::collection($this->profiles->followers($user, $current))]);
    }

    /** GET /api/users/{username}/following */
    public function following(User $user, CurrentUser $current): JsonResponse
    {
        return response()->json(['users' => MemberResource::collection($this->profiles->following($user, $current))]);
    }

    /** POST /api/users/{username}/follow */
    public function follow(User $user, CurrentUser $current): JsonResponse
    {
        Gate::authorize('follow', $user);

        return response()->json($this->profiles->follow($current->user(), $user));
    }

    /** DELETE /api/users/{username}/follow */
    public function unfollow(User $user, CurrentUser $current): JsonResponse
    {
        return response()->json($this->profiles->unfollow($current->user(), $user));
    }
}
