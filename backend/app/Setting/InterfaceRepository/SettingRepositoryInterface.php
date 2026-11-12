<?php

namespace App\Setting\InterfaceRepository;

use App\Models\AppSetting;
use Illuminate\Support\Collection;

interface SettingRepositoryInterface
{
    /**
     * Every stored value as it was saved, by key (a key never changed has none).
     *
     * @return array<string, mixed>
     */
    public function values(): array;

    /**
     * Like `values()`, and holds a write lock on the rows until the surrounding transaction ends (on databases that support it).
     *
     * @return array<string, mixed>
     */
    public function lockedValues(): array;

    /**
     * Every stored row with the admin who last changed it, by key.
     *
     * @return Collection<string, AppSetting>
     */
    public function rows(): Collection;

    /** Stores the value of one key, creating its row or replacing the old value, and records who did it. */
    public function put(string $key, mixed $value, int $actorId): void;
}
