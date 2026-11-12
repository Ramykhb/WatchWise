<?php

namespace App\Admin\InterfaceServices;

use App\Admin\DTOs\Requests\UpdateSettingsData;
use App\Admin\DTOs\Responses\AdminSettingsResponse;
use App\Models\User;

/** What staff do with the app-wide settings, which the Setting module stores and the audit log remembers. */
interface AdminSettingServiceInterface
{
    public function settings(): AdminSettingsResponse;

    /**
     * Writes only the settings whose value really differs and records one `settings.updated` audit entry for each,
     * in the same transaction: if an entry cannot be written, no setting changes. Sending the current values
     * changes and records nothing. Returns every setting as it is afterwards.
     */
    public function update(User $admin, UpdateSettingsData $data): AdminSettingsResponse;
}
