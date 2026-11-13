<?php

namespace App\User\Controllers;

use App\Auth\CurrentUser\CurrentUser;
use App\Auth\Support\AuthCookies;
use App\Models\User;
use App\Support\AccountLink;
use App\User\DTOs\Requests\AccountDeletionLinkData;
use App\User\DTOs\Requests\EmailChangeLinkData;
use App\User\FormRequests\DeleteAccountRequest;
use App\User\FormRequests\ReportUserRequest;
use App\User\FormRequests\RequestEmailChangeRequest;
use App\User\FormRequests\SetPasswordRequest;
use App\User\FormRequests\UpdatePasswordRequest;
use App\User\FormRequests\UpdatePreferencesRequest;
use App\User\FormRequests\UpdateProfileRequest;
use App\User\FormRequests\UploadAvatarRequest;
use App\User\FormRequests\UserSearchRequest;
use App\User\InterfaceServices\AccountDeletionServiceInterface;
use App\User\InterfaceServices\AccountServiceInterface;
use App\User\InterfaceServices\EmailChangeServiceInterface;
use App\User\InterfaceServices\HomeServiceInterface;
use App\User\InterfaceServices\ProfileServiceInterface;
use App\User\Resources\MemberResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        private readonly EmailChangeServiceInterface $emailChange,
        private readonly AccountDeletionServiceInterface $deletion,
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

    /** PUT /api/me/password — changes an existing password; signs out every other device, this one gets fresh cookies. */
    public function updatePassword(UpdatePasswordRequest $request, CurrentUser $current, AuthCookies $cookies): JsonResponse
    {
        $session = $this->account->changePassword($current->user(), $request->toDto(), $request);

        return $cookies->attach(response()->json(['message' => 'Password updated. Other devices have been signed out.']), $session);
    }

    /** PUT /api/me/password/set — first password of an account without one; sessions rotate as for a change. */
    public function setPassword(SetPasswordRequest $request, CurrentUser $current, AuthCookies $cookies): JsonResponse
    {
        $session = $this->account->setPassword($current->user(), $request->toDto(), $request);

        return $cookies->attach(response()->json(['message' => 'Password updated. Other devices have been signed out.']), $session);
    }

    /**
     * PUT /api/me/email — needs the current password; the address only changes once the link mailed to it
     * is opened. Accounts without a password first approve the change from their current address.
     */
    public function requestEmailChange(RequestEmailChangeRequest $request, CurrentUser $current): JsonResponse
    {
        return response()->json($this->emailChange->request($current->user(), $request->toDto()));
    }

    /** POST /api/me/email/resend — mails the link the pending change waits for again (current or new address). */
    public function resendEmailChange(CurrentUser $current): JsonResponse
    {
        return response()->json($this->emailChange->resend($current->user()));
    }

    /** DELETE /api/me/email — drops the pending address. */
    public function cancelEmailChange(CurrentUser $current): JsonResponse
    {
        return response()->json($this->emailChange->cancel($current->user()));
    }

    /**
     * GET /api/me/email/authorize/{id}/{hash} — the approval link mailed to the current address. Changes nothing
     * and needs no session: it only opens the public page whose click POSTs the same signed URL, so a mail scanner
     * or link preview that prefetches it approves nothing.
     */
    public function openEmailChangeAuthorization(Request $request): RedirectResponse
    {
        return redirect(AccountLink::EmailAuthorize->confirmationPage($request));
    }

    /** POST /api/me/email/authorize/{id}/{hash} — the click on that page (signed, no login): approves the change. */
    public function authorizeEmailChange(int $id, string $hash): JsonResponse
    {
        return response()->json(['outcome' => $this->emailChange->authorize(new EmailChangeLinkData($id, $hash))]);
    }

    /**
     * GET /api/me/email/confirm/{id}/{hash} — the link mailed to the new address. Changes nothing and needs no
     * session: it only opens the public page whose click POSTs the same signed URL.
     */
    public function openEmailChangeConfirmation(Request $request): RedirectResponse
    {
        return redirect(AccountLink::EmailConfirm->confirmationPage($request));
    }

    /** POST /api/me/email/confirm/{id}/{hash} — the click on that page (signed, no login): swaps the address in. */
    public function confirmEmailChange(int $id, string $hash): JsonResponse
    {
        return response()->json(['outcome' => $this->emailChange->confirm(new EmailChangeLinkData($id, $hash))]);
    }

    /** GET /api/me/email/cancel/{id}/{hash} — signed "this wasn't me" link to the current address (no login needed). */
    public function cancelEmailChangeFromLink(int $id, string $hash): RedirectResponse
    {
        $outcome = $this->emailChange->cancelFromLink(new EmailChangeLinkData($id, $hash));

        return redirect(AccountLink::EmailCancel->resultPage($outcome->value));
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

    /**
     * DELETE /api/me — with a password that proves ownership, removes the account and everything it owns (FKs
     * cascade) and signs out (200). Otherwise mails a confirmation link to the account's address (202).
     */
    public function destroyMe(DeleteAccountRequest $request, CurrentUser $current, AuthCookies $cookies): JsonResponse
    {
        $result = $this->deletion->request($current->user(), $request->toDto());

        return $result->deleted
            ? $cookies->clear(response()->json($result))
            : response()->json($result, 202);
    }

    /** DELETE /api/me/delete-request — drops the pending deletion, so its emailed links stop working. */
    public function cancelAccountDeletion(CurrentUser $current): JsonResponse
    {
        return response()->json($this->deletion->cancel($current->user()));
    }

    /**
     * GET /api/me/delete/confirm/{id}/{hash} — the link mailed to the account's address. Changes nothing and needs
     * no session: it only opens the public page whose click POSTs the same signed URL, so a mail scanner or link
     * preview that prefetches it deletes nothing.
     */
    public function openAccountDeletionConfirmation(Request $request): RedirectResponse
    {
        return redirect(AccountLink::DeleteConfirm->confirmationPage($request));
    }

    /** POST /api/me/delete/confirm/{id}/{hash} — the click on that page (signed, no login): deletes the account. */
    public function confirmAccountDeletion(int $id, string $hash): JsonResponse
    {
        return response()->json(['outcome' => $this->deletion->confirmFromLink(new AccountDeletionLinkData($id, $hash))]);
    }

    /** GET /api/me/delete/cancel/{id}/{hash} — signed "this wasn't me" link in the same email (no login needed). */
    public function cancelAccountDeletionFromLink(int $id, string $hash): RedirectResponse
    {
        $outcome = $this->deletion->cancelFromLink(new AccountDeletionLinkData($id, $hash));

        return redirect(AccountLink::DeleteCancel->resultPage($outcome->value));
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

    /** POST /api/users/{username}/follow — followers-only and private profiles get a follow request instead. */
    public function follow(User $user, CurrentUser $current): JsonResponse
    {
        Gate::authorize('follow', $user);

        return response()->json($this->profiles->follow($current->user(), $user));
    }

    /** POST /api/users/{username}/report — `reason`: spam, harassment, inappropriate or other; `details` up to 500 characters. 201 when new, 200 when you had already reported them. */
    public function report(ReportUserRequest $request, User $user, CurrentUser $current): JsonResponse
    {
        $result = $this->profiles->report($user, $current->user(), $request->toDto());

        return response()->json($result, $result->created ? 201 : 200);
    }

    /** DELETE /api/users/{username}/follow — also withdraws a pending follow request. */
    public function unfollow(User $user, CurrentUser $current): JsonResponse
    {
        return response()->json($this->profiles->unfollow($current->user(), $user));
    }

    /** POST /api/me/follow-requests/{username}/accept — let them follow your followers-only or private profile. */
    public function acceptFollowRequest(User $user, CurrentUser $current): JsonResponse
    {
        $this->profiles->acceptFollowRequest($current->user(), $user);

        return response()->json(['message' => 'Follow request accepted.']);
    }

    /** DELETE /api/me/follow-requests/{username} */
    public function declineFollowRequest(User $user, CurrentUser $current): JsonResponse
    {
        $this->profiles->declineFollowRequest($current->user(), $user);

        return response()->json(['message' => 'Follow request declined.']);
    }
}
