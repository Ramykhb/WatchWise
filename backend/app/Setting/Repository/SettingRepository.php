<?php

namespace App\Setting\Repository;

use App\Models\AppSetting;
use App\Setting\InterfaceRepository\SettingRepositoryInterface;
use Illuminate\Support\Collection;

class SettingRepository implements SettingRepositoryInterface
{
    public function values(): array
    {
        return AppSetting::query()->pluck('value', 'key')->all();
    }

    public function lockedValues(): array
    {
        return AppSetting::query()->lockForUpdate()->pluck('value', 'key')->all();
    }

    public function rows(): Collection
    {
        return AppSetting::query()->with('updatedBy:id,name,username')->get()->keyBy('key');
    }

    /** One atomic upsert, so two first writes of the same key cannot collide on the primary key. */
    public function put(string $key, mixed $value, int $actorId): void
    {
        $now = now();

        AppSetting::query()->upsert(
            [['key' => $key, 'value' => json_encode($value, JSON_THROW_ON_ERROR), 'updated_by' => $actorId, 'created_at' => $now, 'updated_at' => $now]],
            ['key'],
            ['value', 'updated_by', 'updated_at'],
        );
    }
}
