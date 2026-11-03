<?php

namespace App\User\DTOs\Responses;

use App\Auth\DTOs\Responses\UserResponse;
use App\Models\Notification;
use App\Models\User;
use App\Support\SocialPlaceholderPassword;
use App\User\DTOs\Requests\UpdatePreferencesData;
use JsonSerializable;

/** The signed-in user's own account: details, settings, linked sign-ins and header counts. */
final readonly class AccountResponse implements JsonSerializable
{
    /**
     * @param  array<string, mixed>  $payload
     */
    private function __construct(private array $payload) {}

    public static function fromModel(User $user): self
    {
        $user->loadMissing(['preferences', 'socialAccounts']);
        $settings = $user->preferences?->settings ?? [];

        $privacy = [];
        foreach (UpdatePreferencesData::PRIVACY_KEYS as $key) {
            $privacy[$key] = $user->shares($key);
        }

        // Every notification type is on until the member switches it off.
        $notifications = [];
        foreach (UpdatePreferencesData::NOTIFY_KEYS as $key) {
            $notifications[$key] = (bool) ($settings['notifications'][$key] ?? true);
        }

        return new self([
            'user' => UserResponse::fromModel($user),
            'preferences' => [
                'favorite_genres' => $user->preferences?->favorite_genres ?? [],
                'privacy' => $privacy,
                'integrations' => [...['calendar' => true, 'location' => true, 'weather' => true, 'digest' => false], ...($settings['integrations'] ?? [])],
                'notifications' => $notifications,
            ],
            'connected' => $user->socialAccounts->mapWithKeys(fn ($account) => [$account->provider => $account->created_at?->toJSON()]),
            // False while a social sign-up still has the placeholder password (Settings then disables "current password").
            'has_password' => ! SocialPlaceholderPassword::isStoredFor($user),
            'counts' => [
                // What the bell counts; room read receipts share the table but are not notifications.
                'unread_notifications' => $user->notifications()->whereIn('type', Notification::FEED_TYPES)->whereNull('read_at')->count(),
                'followers' => $user->followers()->count(),
                'following' => $user->following()->count(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->payload;
    }
}
