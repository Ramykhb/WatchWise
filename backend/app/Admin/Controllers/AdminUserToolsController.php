<?php

namespace App\Admin\Controllers;

use App\Admin\FormRequests\AddUserNoteRequest;
use App\Admin\FormRequests\AdminUserNoteIndexRequest;
use App\Admin\FormRequests\BulkSuspendUsersRequest;
use App\Admin\InterfaceServices\AdminUserToolsServiceInterface;
use App\Auth\CurrentUser\CurrentUser;
use App\Models\AdminUserNote;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/** What staff can do for one member beyond the user table: the detail page, notes, sign-out, profile clean-up, resending verification and bulk suspension. */
class AdminUserToolsController
{
    public function __construct(private readonly AdminUserToolsServiceInterface $tools) {}

    /** GET /api/admin/users/{user} */
    public function show(User $user, CurrentUser $current): JsonResponse
    {
        Gate::authorize('admin.users');

        return response()->json(['user' => $this->tools->detail($current->user(), $user)]);
    }

    /** GET /api/admin/users/{user}/notes — newest first. */
    public function notes(AdminUserNoteIndexRequest $request, User $user, CurrentUser $current): JsonResponse
    {
        return response()->json($this->tools->notes($current->user(), $user, $request->toDto()));
    }

    /** POST /api/admin/users/{user}/notes */
    public function addNote(AddUserNoteRequest $request, User $user, CurrentUser $current): JsonResponse
    {
        return response()->json(['note' => $this->tools->addNote($current->user(), $user, $request->toDto())], 201);
    }

    /** DELETE /api/admin/users/{user}/notes/{note} — admins any note, a moderator only their own. */
    public function deleteNote(User $user, AdminUserNote $note, CurrentUser $current): JsonResponse
    {
        Gate::authorize('admin.users');
        $this->tools->deleteNote($current->user(), $user, $note);

        return response()->json(['message' => 'Note deleted.']);
    }

    /** POST /api/admin/users/{user}/sign-out */
    public function signOut(User $user, CurrentUser $current): JsonResponse
    {
        Gate::authorize('admin.users');
        $this->tools->signOut($current->user(), $user);

        return response()->json(['message' => 'User signed out everywhere.']);
    }

    /** DELETE /api/admin/users/{user}/avatar */
    public function removeAvatar(User $user, CurrentUser $current): JsonResponse
    {
        Gate::authorize('admin.users');
        $this->tools->removeAvatar($current->user(), $user);

        return response()->json(['message' => 'Avatar removed.']);
    }

    /** DELETE /api/admin/users/{user}/bio */
    public function removeBio(User $user, CurrentUser $current): JsonResponse
    {
        Gate::authorize('admin.users');
        $this->tools->removeBio($current->user(), $user);

        return response()->json(['message' => 'Bio removed.']);
    }

    /** POST /api/admin/users/{user}/resend-verification */
    public function resendVerification(User $user, CurrentUser $current): JsonResponse
    {
        Gate::authorize('admin.users');
        $this->tools->resendVerification($current->user(), $user);

        return response()->json(['message' => 'Verification email sent.']);
    }

    /** POST /api/admin/users/bulk-suspend */
    public function bulkSuspend(BulkSuspendUsersRequest $request, CurrentUser $current): JsonResponse
    {
        return response()->json($this->tools->bulkSuspend($current->user(), $request->toDto()));
    }
}
