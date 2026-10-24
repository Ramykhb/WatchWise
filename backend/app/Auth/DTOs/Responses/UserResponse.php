<?php

namespace App\Auth\DTOs\Responses;

use App\Models\User;
use JsonSerializable;

/** The public shape of a user in API responses. */
final readonly class UserResponse implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $username,
        public string $email,
        public ?string $avatar,
        public ?string $bio,
        public ?string $location,
        public string $role,
        public string $profileVisibility,
        public bool $isActive,
        public ?string $emailVerifiedAt,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            username: $user->username,
            email: $user->email,
            avatar: $user->avatar,
            bio: $user->bio,
            location: $user->location,
            role: $user->role,
            profileVisibility: $user->profile_visibility,
            isActive: (bool) $user->is_active,
            emailVerifiedAt: $user->email_verified_at?->toJSON(),
            createdAt: $user->created_at?->toJSON(),
            updatedAt: $user->updated_at?->toJSON(),
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
            'bio' => $this->bio,
            'location' => $this->location,
            'role' => $this->role,
            'profile_visibility' => $this->profileVisibility,
            'is_active' => $this->isActive,
            'email_verified_at' => $this->emailVerifiedAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
