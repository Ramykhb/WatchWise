<?php

namespace App\Admin\Controllers;

use App\Admin\FormRequests\AdminListIndexRequest;
use App\Admin\FormRequests\RemoveListItemRequest;
use App\Admin\FormRequests\UpdateListVisibilityRequest;
use App\Admin\InterfaceServices\AdminListServiceInterface;
use App\Auth\CurrentUser\CurrentUser;
use App\Models\MovieList;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/** Every member's movie lists for staff: read them, make one private, remove a title or (admins) delete one. */
class AdminListController
{
    public function __construct(private readonly AdminListServiceInterface $lists) {}

    /** GET /api/admin/lists */
    public function index(AdminListIndexRequest $request): JsonResponse
    {
        return response()->json($this->lists->lists($request->toDto()));
    }

    /** GET /api/admin/lists/{list} */
    public function show(MovieList $list): JsonResponse
    {
        Gate::authorize('admin.lists');

        return response()->json(['list' => $this->lists->list($list)]);
    }

    /** PATCH /api/admin/lists/{list} — `visibility` may only be `private`. */
    public function update(UpdateListVisibilityRequest $request, MovieList $list, CurrentUser $current): JsonResponse
    {
        return response()->json(['list' => $this->lists->makePrivate($current->user(), $list, $request->toDto())]);
    }

    /** DELETE /api/admin/lists/{list}/items/{tmdbId}?type=movie|tv */
    public function removeItem(RemoveListItemRequest $request, MovieList $list, CurrentUser $current): JsonResponse
    {
        return response()->json(['list' => $this->lists->removeItem($current->user(), $list, $request->toDto())]);
    }

    /** DELETE /api/admin/lists/{list} */
    public function destroy(MovieList $list, CurrentUser $current): JsonResponse
    {
        Gate::authorize('admin.lists.delete');
        $this->lists->delete($current->user(), $list);

        return response()->json(['message' => 'List deleted.']);
    }
}
