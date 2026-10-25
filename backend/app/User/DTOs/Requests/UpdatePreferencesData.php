<?php

namespace App\User\DTOs\Requests;

final readonly class UpdatePreferencesData
{
    /** @var list<string> */
    public const PRIVACY_KEYS = ['ratings', 'reviews', 'lists', 'watchlist', 'history', 'following'];

    /** @var list<string> */
    public const INTEGRATION_KEYS = ['calendar', 'location', 'weather', 'digest'];

    /** @var list<string> */
    public const NOTIFY_KEYS = ['nights', 'mentions', 'social', 'releases'];

    /**
     * A null group or genre list was not sent and is left alone.
     *
     * @param  list<string>|null  $favoriteGenres
     * @param  array<string, bool>|null  $privacy
     * @param  array<string, bool>|null  $integrations
     * @param  array<string, bool>|null  $notifications
     */
    public function __construct(
        public ?array $favoriteGenres,
        public ?array $privacy,
        public ?array $integrations,
        public ?array $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            $validated['favorite_genres'] ?? null,
            $validated['privacy'] ?? null,
            $validated['integrations'] ?? null,
            $validated['notifications'] ?? null,
        );
    }

    /**
     * The settings groups that were sent, each with the keys it may change.
     *
     * @return array<string, array{0: array<string, bool>, 1: list<string>}>
     */
    public function groups(): array
    {
        return array_filter([
            'privacy' => $this->privacy === null ? null : [$this->privacy, self::PRIVACY_KEYS],
            'integrations' => $this->integrations === null ? null : [$this->integrations, self::INTEGRATION_KEYS],
            'notifications' => $this->notifications === null ? null : [$this->notifications, self::NOTIFY_KEYS],
        ]);
    }
}
