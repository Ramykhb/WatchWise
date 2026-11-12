<?php

namespace App\Admin\Controllers;

use App\Admin\FormRequests\UpdateSettingsRequest;
use App\Admin\InterfaceServices\AdminSettingServiceInterface;
use App\Auth\CurrentUser\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/** The app-wide settings (registration, review approval, moderation, rooms, announcement), for admins only. */
class AdminSettingController
{
    public function __construct(private readonly AdminSettingServiceInterface $settings) {}

    /** GET /api/admin/settings */
    public function index(): JsonResponse
    {
        Gate::authorize('admin.settings');

        return response()->json($this->settings->settings());
    }

    /** PATCH /api/admin/settings — any subset of the settings. */
    public function update(UpdateSettingsRequest $request, CurrentUser $current): JsonResponse
    {
        return response()->json($this->settings->update($current->user(), $request->toDto()));
    }
}
