<?php

namespace App\Setting\Controllers;

use App\Setting\InterfaceServices\SettingServiceInterface;
use Illuminate\Http\JsonResponse;

/** The site announcement, public so signed-out visitors see it too. */
class AnnouncementController
{
    public function __construct(private readonly SettingServiceInterface $settings) {}

    /** GET /api/announcement — `{"announcement": null}` while there is nothing to show. */
    public function show(): JsonResponse
    {
        return response()->json(['announcement' => $this->settings->activeAnnouncement()]);
    }
}
