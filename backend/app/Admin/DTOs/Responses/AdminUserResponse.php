<?php

namespace App\Admin\DTOs\Responses;

use App\Models\User;
use JsonSerializable;

/** A member as the admin user table shows them. */
final readonly class AdminUserResponse implements JsonSerializable
{
    /**
     * @param  'active'|'unverified'|'inactive'  $status
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $username,
        public string $email,
        public ?string $avatar,
        public string $role,
        public bool $isActive,
        public string $status,
        public int $reviewsCount,
        public ?string $emailVerifiedAt,
        public ?string $lastActiveAt,
        public ?string $createdAt,
    ) {}

    /** Needs the user's `reviews_count` loaded (`withCount`/`loadCount`); it reads as 0 otherwise. */
    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            username: $user->username,
            email: $user->email,
            avatar: $user->avatar,
            role: $user->role,
            isActive: (bool) $user->is_active,
            status: match (true) {
                ! $user->is_active => 'inactive',
                $user->email_verified_at === null => 'unverified',
                default => 'active',
            },
            reviewsCount: (int) ($user->reviews_count ?? 0),
            emailVerifiedAt: $user->email_verified_at?->toJSON(),
            lastActiveAt: $user->last_active_at?->toJSON(),
            createdAt: $user->created_at?->toJSON(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'avatar' => $this->avatar,
            'role' => $this->role,
            'is_active' => $this->isActive,
            'status' => $this->status,
            'reviews_count' => $this->reviewsCount,
            'email_verified_at' => $this->emailVerifiedAt,
            'last_active_at' => $this->lastActiveAt,
            'created_at' => $this->createdAt,
        ];
    }
}
