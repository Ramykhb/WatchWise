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

        return new self([
            'user' => UserResponse::fromModel($user),
            'preferences' => [
                'favorite_genres' => $user->preferences?->favorite_genres ?? [],
                'privacy' => $privacy,
                'integrations' => [...['calendar' => true, 'location' => true, 'weather' => true, 'digest' => false], ...($settings['integrations'] ?? [])],
                'notifications' => [...['nights' => true, 'mentions' => true, 'social' => true, 'releases' => false], ...($settings['notifications'] ?? [])],
            ],
            'connected' => $user->socialAccounts->mapWithKeys(fn ($account) => [$account->provider => $account->created_at?->toJSON()]),
            // False while a social sign-up still has the placeholder password (Settings then disables "current password").
            'has_password' => ! SocialPlaceholderPassword::isStoredFor($user),
            'counts' => [
                // Room messages waiting to be read are read receipts, not notifications.
                'unread_notifications' => $user->notifications()->where('type', '!=', Notification::ROOM_MESSAGE)->whereNull('read_at')->count(),
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
