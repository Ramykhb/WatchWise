<?php

namespace App\Setting\InterfaceServices;

use App\Models\User;
use App\Setting\DTOs\Responses\AnnouncementResponse;
use App\Setting\DTOs\Responses\SettingEntryResponse;
use InvalidArgumentException;

/**
 * The app-wide settings. Other modules read them through the typed getters; staff read and change them through
 * `entries()` and `update()`. Reads are cached for a short time and a write retires the cache, so a change shows
 * on the next request.
 */
interface SettingServiceInterface
{
    /** Whether new accounts may be created, by password sign-up or by a first social sign-in. */
    public function registrationOpen(): bool;

    /** Whether every new or edited review with text waits for a moderator, whatever the moderation says. */
    public function reviewsRequireApproval(): bool;

    /** Whether new review text is classified by the AI moderation. */
    public function reviewAutoModeration(): bool;

    /** Whether members may create discussion rooms. */
    public function roomsCreationEnabled(): bool;

    /** The announcement to show right now: null while it is off or has expired. */
    public function activeAnnouncement(): ?AnnouncementResponse;

    /**
     * Every setting in registry order with its current value (the default while nothing valid is stored).
     *
     * @return list<SettingEntryResponse>
     */
    public function entries(): array;

    /**
     * Stores the submitted settings, writing only those whose value really differs from the current one. Returns
     * what changed, by key; an empty array means nothing was written. The values must already be valid (the
     * Admin form request validates them): a key or value that does not fit the registry is a programming error.
     *
     * @param  array<string, bool|array<string, mixed>>  $values  by `SettingKey` value
     * @return array<string, array{from: bool|array<string, mixed>, to: bool|array<string, mixed>}>
     *
     * @throws InvalidArgumentException
     */
    public function update(User $actor, array $values): array;
}
