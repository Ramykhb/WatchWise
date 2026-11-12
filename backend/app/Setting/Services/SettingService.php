<?php

namespace App\Setting\Services;

use App\Models\User;
use App\Setting\DTOs\Responses\AnnouncementResponse;
use App\Setting\DTOs\Responses\SettingEntryResponse;
use App\Setting\InterfaceRepository\SettingRepositoryInterface;
use App\Setting\InterfaceServices\SettingServiceInterface;
use App\Setting\Support\SettingKey;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SettingService implements SettingServiceInterface
{
    /** Seconds the resolved settings are reused before they are read again. */
    private const CACHE_TTL = 60;

    /** Cache key of every resolved setting, as a plain array (`cache.serializable_classes` is `false`). */
    private const CACHE_KEY = 'settings:resolved';

    public function __construct(private readonly SettingRepositoryInterface $settings) {}

    public function registrationOpen(): bool
    {
        return $this->flag(SettingKey::RegistrationOpen);
    }

    public function reviewsRequireApproval(): bool
    {
        return $this->flag(SettingKey::ReviewsRequireApproval);
    }

    public function reviewAutoModeration(): bool
    {
        return $this->flag(SettingKey::ReviewAutoModeration);
    }

    public function roomsCreationEnabled(): bool
    {
        return $this->flag(SettingKey::RoomsCreationEnabled);
    }

    public function activeAnnouncement(): ?AnnouncementResponse
    {
        $announcement = $this->value(SettingKey::Announcement);
        $expiresAt = $announcement['expires_at'];

        if (! $announcement['enabled'] || ($expiresAt !== null && Carbon::parse($expiresAt)->isPast())) {
            return null;
        }

        return new AnnouncementResponse($announcement['message'], $announcement['level'], $expiresAt);
    }

    public function entries(): array
    {
        $rows = $this->settings->rows();

        return array_map(
            fn (SettingKey $key): SettingEntryResponse => SettingEntryResponse::fromRow($key, $rows->get($key->value)),
            SettingKey::cases(),
        );
    }

    public function update(User $actor, array $values): array
    {
        $changes = DB::transaction(function () use ($actor, $values): array {
            $stored = $this->settings->lockedValues();
            $changes = [];

            foreach ($values as $name => $submitted) {
                $key = SettingKey::from($name);
                $to = $key->normalize($submitted) ?? throw new InvalidArgumentException("The value of the {$name} setting is not valid.");
                $from = $key->resolve($stored[$name] ?? null);

                if ($from === $to) {
                    continue;
                }

                $this->settings->put($name, $to, $actor->id);
                $changes[$name] = ['from' => $from, 'to' => $to];
            }

            return $changes;
        });

        if ($changes !== []) {
            DB::afterCommit(fn () => Cache::forget(self::CACHE_KEY));
        }

        return $changes;
    }

    private function flag(SettingKey $key): bool
    {
        return (bool) $this->value($key);
    }

    /**
     * The current value of one key. A cached array written before the key existed (a deploy that added one) has
     * no entry for it, and then the key's default applies.
     *
     * @return bool|array<string, mixed>
     */
    private function value(SettingKey $key): bool|array
    {
        return $this->cached()[$key->value] ?? $key->default();
    }

    /**
     * Every setting's current value by key, cached as a plain array.
     *
     * @return array<string, bool|array<string, mixed>>
     */
    private function cached(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            $stored = $this->settings->values();
            $resolved = [];

            foreach (SettingKey::cases() as $key) {
                $resolved[$key->value] = $key->resolve($stored[$key->value] ?? null);
            }

            return $resolved;
        });
    }
}
