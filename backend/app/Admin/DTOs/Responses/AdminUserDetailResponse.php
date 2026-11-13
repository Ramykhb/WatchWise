<?php

namespace App\Admin\DTOs\Responses;

use App\Models\User;
use JsonSerializable;

/**
 * One member as the admin user page shows them: the table row plus profile, activity counts, linked sign-in
 * providers, open sessions, the suspension, how many staff notes exist and the latest audit entries. Never
 * password data, tokens, a pending email or deletion state.
 */
final readonly class AdminUserDetailResponse implements JsonSerializable
{
    /**
     * @param  array{reviews_with_text: int, ratings: int, lists: int, followers: int, following: int, rooms_created: int, reports_against_open: int, reports_filed: int}  $counts
     * @param  list<string>  $socialProviders  provider names only
     * @param  list<AdminAuditLogResponse>|null  $recentAudit  null for a moderator, who may not read the audit log
     */
    public function __construct(
        public AdminUserResponse $row,
        public ?string $bio,
        public ?string $avatar,
        public string $profileVisibility,
        public array $counts,
        public array $socialProviders,
        public int $activeSessions,
        public ?string $suspensionReason,
        public ?string $suspendedUntil,
        public bool $suspended,
        public int $notesCount,
        public ?array $recentAudit,
    ) {}

    /**
     * @param  array{reviews_with_text: int, ratings: int, lists: int, followers: int, following: int, rooms_created: int, reports_against_open: int, reports_filed: int}  $counts
     * @param  list<string>  $socialProviders
     * @param  list<AdminAuditLogResponse>|null  $recentAudit
     */
    public static function fromModel(User $user, array $counts, array $socialProviders, int $activeSessions, int $notesCount, ?array $recentAudit): self
    {
        return new self(
            row: AdminUserResponse::fromModel($user, $counts['reviews_with_text']),
            bio: $user->bio,
            avatar: $user->avatar,
            profileVisibility: $user->profile_visibility,
            counts: $counts,
            socialProviders: $socialProviders,
            activeSessions: $activeSessions,
            suspensionReason: $user->suspension_reason,
            suspendedUntil: $user->suspended_until?->toJSON(),
            suspended: ! $user->is_active,
            notesCount: $notesCount,
            recentAudit: $recentAudit,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            ...$this->row->jsonSerialize(),
            'profile' => [
                'bio' => $this->bio,
                'avatar' => $this->avatar,
                'profile_visibility' => $this->profileVisibility,
            ],
            'counts' => $this->counts,
            'social_providers' => $this->socialProviders,
            'active_sessions' => $this->activeSessions,
            'suspension' => $this->suspended ? ['reason' => $this->suspensionReason, 'until' => $this->suspendedUntil] : null,
            'notes_count' => $this->notesCount,
            'recent_audit' => $this->recentAudit,
        ];
    }
}
