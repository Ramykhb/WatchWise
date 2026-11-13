<?php

namespace Tests\Feature\Setting;

use App\Models\User;
use App\Setting\InterfaceServices\SettingServiceInterface;

/** Changes app-wide settings the way an admin does, through the service, so the cache is retired like in production. */
trait ChangesSettings
{
    /**
     * @param  array<string, bool|array<string, mixed>>  $values  by setting key
     */
    private function changeSettings(array $values): void
    {
        app(SettingServiceInterface::class)->update(User::factory()->admin()->create(), $values);
    }

    /**
     * @return array{enabled: bool, message: string, level: string, expires_at: string|null}
     */
    private function announcement(bool $enabled = true, string $message = 'Maintenance tonight.', string $level = 'warning', ?string $expiresAt = null): array
    {
        return ['enabled' => $enabled, 'message' => $message, 'level' => $level, 'expires_at' => $expiresAt];
    }
}
