<?php

namespace App\Admin\DTOs\Responses;

use App\Models\User;
use JsonSerializable;

/** A member as the admin user table shows them. */
final readonly class AdminUserResponse implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $username,
        public string $email,
        public ?string $avatar,
        public string $role,
        public bool $isActive,
        public ?string $emailVerifiedAt,
        public ?string $createdAt,
    ) {}

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
            emailVerifiedAt: $user->email_verified_at?->toJSON(),
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
            'email_verified_at' => $this->emailVerifiedAt,
            'created_at' => $this->createdAt,
        ];
    }
}
