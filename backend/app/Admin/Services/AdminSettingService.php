<?php

namespace App\Admin\Services;

use App\Admin\DTOs\Requests\UpdateSettingsData;
use App\Admin\DTOs\Responses\AdminSettingsResponse;
use App\Admin\InterfaceServices\AdminAuditServiceInterface;
use App\Admin\InterfaceServices\AdminSettingServiceInterface;
use App\Admin\Support\AdminAction;
use App\Models\User;
use App\Setting\InterfaceServices\SettingServiceInterface;
use App\Setting\Support\SettingKey;
use Illuminate\Support\Facades\DB;

class AdminSettingService implements AdminSettingServiceInterface
{
    /** Announcement fields whose old and new value the audit log keeps; the message is only marked changed. */
    private const LOGGED_ANNOUNCEMENT_FIELDS = ['enabled', 'level', 'expires_at'];

    public function __construct(
        private readonly SettingServiceInterface $settings,
        private readonly AdminAuditServiceInterface $audit,
    ) {}

    public function settings(): AdminSettingsResponse
    {
        return new AdminSettingsResponse($this->settings->entries());
    }

    public function update(User $admin, UpdateSettingsData $data): AdminSettingsResponse
    {
        DB::transaction(function () use ($admin, $data): void {
            foreach ($this->settings->update($admin, $data->values) as $name => $change) {
                $key = SettingKey::from($name);

                $this->audit->record($admin, AdminAction::SettingsUpdated, null, $name, $this->auditChanges($key, $change['from'], $change['to']));
            }
        });

        return $this->settings();
    }

    /**
     * What the audit entry keeps of one change: `value: {from, to}`, or for the announcement each of its fields
     * that changed, with the message only marked `{changed: true}` (it is free text, never logged).
     *
     * @param  bool|array<string, mixed>  $from
     * @param  bool|array<string, mixed>  $to
     * @return array<string, array<string, mixed>>
     */
    private function auditChanges(SettingKey $key, bool|array $from, bool|array $to): array
    {
        if ($key !== SettingKey::Announcement) {
            return ['value' => ['from' => $from, 'to' => $to]];
        }

        $changes = [];

        foreach (self::LOGGED_ANNOUNCEMENT_FIELDS as $field) {
            if ($from[$field] !== $to[$field]) {
                $changes[$field] = ['from' => $from[$field], 'to' => $to[$field]];
            }
        }

        if ($from['message'] !== $to['message']) {
            $changes['message'] = ['changed' => true];
        }

        return $changes;
    }
}
